<?php
require __DIR__ . '/lib.php';
require_admin();
date_default_timezone_set('Asia/Kolkata');
$db = dbc();

/* Creates the table the first time this page opens. */
try {
    $db->query("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        spent_on DATE NOT NULL,
        category VARCHAR(40) NOT NULL,
        description VARCHAR(120) NOT NULL,
        payee VARCHAR(80) NOT NULL DEFAULT '',
        method VARCHAR(10) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        voided_at TIMESTAMP NULL DEFAULT NULL,
        KEY k_date (spent_on)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $x) {
    exit('Could not create the expenses table: ' . e($x->getMessage()));
}

$CATS = ['Rent', 'Salaries', 'Electricity & water', 'Internet & phone', 'Marketing & ads', 'Software & subscriptions', 'Travel', 'Office supplies', 'Repairs & maintenance', 'Taxes & fees', 'Other'];
$METHODS = ['Cash', 'UPI', 'Bank', 'Card'];

function exp_csrf_ok(): bool { return hash_equals((string)csrf(), (string)($_POST['csrf'] ?? '')); }
function exp_inr($n): string {
    $s = number_format((float)$n, 2, '.', ''); [$i, $d] = explode('.', $s);
    $neg = $i[0] === '-' ? '-' : ''; $i = ltrim($i, '-');
    $last = substr($i, -3); $rest = substr($i, 0, -3);
    if ($rest !== '') $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',';
    return '₹' . $neg . $rest . $last . '.' . $d;
}
function exp_run(mysqli $db, string $sql, string $types, array $params) {
    $st = $db->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    return $st->get_result();
}
function exp_csv_cell($v): string { $v = (string)$v; return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; }

/* ---------- filters ---------- */
$month = (isset($_GET['month']) && preg_match('/^(all|\d{4}-(0[1-9]|1[0-2]))$/', $_GET['month'])) ? $_GET['month'] : date('Y-m');
$cat = in_array($_GET['category'] ?? '', $CATS, true) ? $_GET['category'] : '';
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
$w = ['voided_at IS NULL']; $t = ''; $p = [];
if ($month !== 'all') { $w[] = 'spent_on >= ? AND spent_on < DATE_ADD(?, INTERVAL 1 MONTH)'; $t .= 'ss'; $p[] = $month . '-01'; $p[] = $month . '-01'; }
if ($cat !== '') { $w[] = 'category = ?'; $t .= 's'; $p[] = $cat; }
if ($q !== '') { $w[] = '(description LIKE ? OR payee LIKE ?)'; $t .= 'ss'; $like = '%' . addcslashes($q, '%_\\') . '%'; $p[] = $like; $p[] = $like; }
$W = implode(' AND ', $w);

/* ---------- CSV download (must happen before any page output) ---------- */
if (isset($_GET['export'])) {
    $rs = exp_run($db, "SELECT spent_on, category, description, payee, method, amount FROM expenses WHERE $W ORDER BY spent_on DESC, id DESC LIMIT 5000", $t, $p);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newtri-expenses-' . $month . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', 'Category', 'Description', 'Paid to', 'Paid by', 'Amount (INR)']);
    while ($r = $rs->fetch_assoc()) fputcsv($out, [$r['spent_on'], exp_csv_cell($r['category']), exp_csv_cell($r['description']), exp_csv_cell($r['payee']), $r['method'], $r['amount']]);
    exit;
}

/* ---------- add / edit / remove ---------- */
$err = '';
$form = ['id' => 0, 'date' => date('Y-m-d'), 'category' => $CATS[0], 'description' => '', 'payee' => '', 'method' => 'Cash', 'amount' => ''];
$qs = fn(string $m, string $flag) => 'expenses.php?month=' . urlencode($m) . '&ok=' . $flag;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!exp_csrf_ok()) { http_response_code(400); exit('Your session expired. Go back, refresh the page and try again.'); }
    $do = $_POST['do'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $backM = preg_match('/^(all|\d{4}-\d{2})$/', $_POST['back'] ?? '') ? $_POST['back'] : date('Y-m');
    if ($do === 'void') {
        $st = $db->prepare('UPDATE expenses SET voided_at = NOW() WHERE id = ? AND voided_at IS NULL');
        $st->bind_param('i', $id); $st->execute();
        header('Location: ' . $qs($backM, 'removed')); exit;
    }
    $form = ['id' => $id, 'date' => trim((string)($_POST['date'] ?? '')), 'category' => (string)($_POST['category'] ?? ''), 'description' => trim((string)($_POST['description'] ?? '')),
             'payee' => trim((string)($_POST['payee'] ?? '')), 'method' => (string)($_POST['method'] ?? ''), 'amount' => trim((string)($_POST['amount'] ?? ''))];
    $d = DateTime::createFromFormat('Y-m-d', $form['date']);
    $raw = str_replace(',', '', $form['amount']);
    if (!$d || $d->format('Y-m-d') !== $form['date']) $err = 'Choose a valid date.';
    elseif (!in_array($form['category'], $CATS, true)) $err = 'Choose a category.';
    elseif ($form['description'] === '' || mb_strlen($form['description']) > 120) $err = 'Write what the expense was for (up to 120 characters).';
    elseif (mb_strlen($form['payee']) > 80) $err = 'The "paid to" name is too long (80 characters at most).';
    elseif (!in_array($form['method'], $METHODS, true)) $err = 'Choose how it was paid.';
    elseif (!is_numeric($raw) || (float)$raw <= 0 || (float)$raw > 99999999.99) $err = 'Enter an amount greater than zero.';
    if (!$err) {
        $amt = number_format(round((float)$raw, 2), 2, '.', '');
        if ($do === 'update' && $id > 0) {
            $st = $db->prepare('UPDATE expenses SET spent_on=?, category=?, description=?, payee=?, method=?, amount=? WHERE id=? AND voided_at IS NULL');
            $st->bind_param('ssssssi', $form['date'], $form['category'], $form['description'], $form['payee'], $form['method'], $amt, $id);
            $flag = 'saved';
        } else {
            $st = $db->prepare('INSERT INTO expenses (spent_on, category, description, payee, method, amount) VALUES (?,?,?,?,?,?)');
            $st->bind_param('ssssss', $form['date'], $form['category'], $form['description'], $form['payee'], $form['method'], $amt);
            $flag = 'added';
        }
        $st->execute();
        header('Location: ' . $qs($backM === 'all' ? 'all' : substr($form['date'], 0, 7), $flag)); exit;
    }
} elseif (($eid = (int)($_GET['edit'] ?? 0)) > 0) {
    $r = exp_run($db, 'SELECT * FROM expenses WHERE id = ? AND voided_at IS NULL', 'i', [$eid])->fetch_assoc();
    if ($r) $form = ['id' => $eid, 'date' => $r['spent_on'], 'category' => $r['category'], 'description' => $r['description'], 'payee' => $r['payee'], 'method' => $r['method'], 'amount' => $r['amount']];
}

/* ---------- numbers for the page ---------- */
$agg = exp_run($db, "SELECT COUNT(*) n, COALESCE(SUM(amount),0) total FROM expenses WHERE $W", $t, $p)->fetch_assoc();
$total = (float)$agg['total']; $count = (int)$agg['n'];
$byCat = []; $rs = exp_run($db, "SELECT category, SUM(amount) s FROM expenses WHERE $W GROUP BY category ORDER BY s DESC", $t, $p);
while ($r = $rs->fetch_assoc()) $byCat[$r['category']] = (float)$r['s'];
$topCat = $byCat ? array_key_first($byCat) : '—';
$todayTotal = (float)exp_run($db, 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE voided_at IS NULL AND spent_on = ?', 's', [date('Y-m-d')])->fetch_row()[0];
$list = exp_run($db, "SELECT id, spent_on, category, description, payee, method, amount FROM expenses WHERE $W ORDER BY spent_on DESC, id DESC LIMIT 500", $t, $p);
$flash = ['added' => 'Expense saved.', 'saved' => 'Changes saved.', 'removed' => 'Expense removed.'][$_GET['ok'] ?? ''] ?? '';
$monthLabel = $month === 'all' ? 'All months' : date('F Y', strtotime($month . '-01'));
$csvLink = 'expenses.php?' . http_build_query(['month' => $month, 'category' => $cat, 'q' => $q, 'export' => 1]);

page_head('Expenses - Admin'); top_bar();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2a5b;--navy2:#123a7d;--blue:#1f6feb;--blue-soft:#e8f0fe;--bg:#f2f5fa;--card:#fff;--line:#e3e8f0;--text:#1c2433;--muted:#6b7587;--green:#12805c;--green-bg:#dcf5ea;--red:#c0272d;--red-bg:#fde4e4}
body{background:var(--bg);color:var(--text);font-family:'Inter','Segoe UI',system-ui,sans-serif;font-size:15px;line-height:1.5}
.wrap{max-width:1280px;margin:0 auto;padding:24px 20px 70px}
.hero{border-radius:18px;padding:28px 34px;margin-bottom:22px;color:#fff;background:linear-gradient(120deg,var(--navy),var(--navy2) 55%,#1f6feb)}
.hero h1{margin:0 0 4px;font-size:28px;font-weight:800;letter-spacing:-.02em}.hero p{margin:0;opacity:.85}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat{background:var(--card);border:1px solid var(--line);border-left:5px solid var(--blue);border-radius:14px;padding:16px 20px}
.stat b{display:block;font-size:26px;line-height:1.2;color:var(--navy)}.stat span{font-size:13px;color:var(--muted)}
.stat.t2{border-left-color:#7a5af8}.stat.t3{border-left-color:#e8a317}.stat.t4{border-left-color:#1aa377}
.wrap .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px 26px;margin-bottom:24px}
.wrap .card h2{margin:0 0 10px;font-size:20px;font-weight:700;color:var(--navy)}
.muted{color:var(--muted);font-size:14px}.ok{color:var(--green);font-weight:600}.err{color:var(--red);font-weight:600}
.wrap .btn{display:inline-block;background:var(--blue);color:#fff;border:1px solid var(--blue);border-radius:10px;padding:10px 18px;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.wrap .btn:hover{background:#1558c0}.wrap .btn.sm{padding:6px 12px;font-size:13px;border-radius:8px}
.wrap .btn.alt{background:var(--blue-soft);color:var(--navy);border-color:transparent}.wrap .btn.red{background:#fff;color:var(--red);border-color:#f0b4b7}
.wrap :focus-visible{outline:3px solid rgba(31,111,235,.4);outline-offset:2px}
.fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end}
.fgrid label,.filters label{display:flex;flex-direction:column;gap:4px;font-size:13px;color:var(--muted)}
.wrap input,.wrap select{padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font:inherit;color:var(--text);width:100%}
.filters{display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin-bottom:14px}.filters label{flex:1 1 170px}
.bar{display:grid;grid-template-columns:200px 1fr 170px;gap:12px;align-items:center;margin:8px 0}
.bar i{display:block;height:14px;border-radius:6px;background:var(--blue);min-width:3px}.bar .r{text-align:right}
.wrap .tbl{overflow-x:auto;border:1px solid var(--line);border-radius:12px}
.wrap .tbl table{width:100%;border-collapse:collapse;min-width:760px;background:#fff}
.wrap .tbl th{background:#f1f5fb;color:var(--navy);font-size:13px;font-weight:700;text-align:left;padding:12px 14px;border-bottom:1px solid var(--line);white-space:nowrap}
.wrap .tbl td{padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:top}.wrap .tbl tr:last-child td{border-bottom:0}
.wrap .tbl td.r,.wrap .tbl th.r{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.wrap .tbl form{display:inline}
.empty{padding:26px;text-align:center;color:var(--muted);background:#f8fafd;border:1px dashed var(--line);border-radius:12px}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}.bar{grid-template-columns:1fr}.bar .r{text-align:left}}
</style>

<div class="wrap">
  <div class="hero"><h1>Expenses</h1><p>Record what NEWTRI spends and see where the money goes. For admin reference.</p></div>

  <div class="stats">
    <div class="stat"><b><?= e(exp_inr($total)) ?></b><span>Total — <?= e($monthLabel) ?><?= ($cat || $q) ? ' (filtered)' : '' ?></span></div>
    <div class="stat t2"><b><?= $count ?></b><span>Entries</span></div>
    <div class="stat t3"><b><?= e($topCat) ?></b><span>Biggest category</span></div>
    <div class="stat t4"><b><?= e(exp_inr($todayTotal)) ?></b><span>Spent today</span></div>
  </div>

  <div class="card" id="form">
    <h2><?= $form['id'] ? 'Edit expense' : 'Add an expense' ?></h2>
    <?php if ($flash): ?><p class="ok"><?= e($flash) ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err" role="alert"><?= e($err) ?></p><?php endif; ?>
    <form method="post" action="expenses.php?month=<?= e(urlencode($month)) ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="back" value="<?= e($month) ?>">
      <input type="hidden" name="do" value="<?= $form['id'] ? 'update' : 'add' ?>"><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
      <div class="fgrid">
        <label>Date<input type="date" name="date" value="<?= e($form['date']) ?>" required></label>
        <label>Category<select name="category"><?php foreach ($CATS as $c): ?><option <?= $c === $form['category'] ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
        <label>What was it for<input name="description" maxlength="120" value="<?= e($form['description']) ?>" placeholder="e.g. October office rent" required></label>
        <label>Paid to (optional)<input name="payee" maxlength="80" value="<?= e($form['payee']) ?>"></label>
        <label>Amount ₹<input name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" value="<?= e($form['amount']) ?>" required></label>
        <label>Paid by<select name="method"><?php foreach ($METHODS as $m): ?><option <?= $m === $form['method'] ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></label>
        <div><button class="btn"><?= $form['id'] ? 'Save changes' : 'Save expense' ?></button>
          <?php if ($form['id']): ?> <a class="btn alt" href="expenses.php?month=<?= e(urlencode($month)) ?>">Cancel</a><?php endif; ?></div>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Where the money went</h2>
    <?php if ($byCat): $max = max($byCat); foreach ($byCat as $k => $v): ?>
      <div class="bar"><span><?= e($k) ?></span><div><i style="width:<?= max(1, round($v / $max * 100)) ?>%"></i></div>
        <span class="r"><?= e(exp_inr($v)) ?> (<?= $total > 0 ? round($v / $total * 100) : 0 ?>%)</span></div>
    <?php endforeach; else: ?><div class="empty">No expenses for this filter yet.</div><?php endif; ?>
  </div>

  <div class="card">
    <h2>All expenses</h2>
    <form class="filters" method="get" action="expenses.php">
      <label>Month<input type="month" name="month" value="<?= $month === 'all' ? '' : e($month) ?>"></label>
      <label>Category<select name="category"><option value="">All categories</option><?php foreach ($CATS as $c): ?><option <?= $c === $cat ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
      <label>Search<input type="search" name="q" value="<?= e($q) ?>" placeholder="Description or name"></label>
      <div><button class="btn">Apply</button> <a class="btn alt" href="expenses.php?month=all">All months</a> <a class="btn alt" href="<?= e($csvLink) ?>">Download CSV</a></div>
    </form>
    <div class="tbl"><table>
      <tr><th>Date</th><th>Category</th><th>Details</th><th>Paid by</th><th class="r">Amount</th><th>Actions</th></tr>
      <?php $shown = 0; while ($r = $list->fetch_assoc()): $shown++; ?>
      <tr>
        <td><?= e(date('d/m/Y', strtotime($r['spent_on']))) ?></td>
        <td><?= e($r['category']) ?></td>
        <td><?= e($r['description']) ?><?= $r['payee'] !== '' ? '<br><span class="muted">' . e($r['payee']) . '</span>' : '' ?></td>
        <td><?= e($r['method']) ?></td>
        <td class="r"><?= e(exp_inr($r['amount'])) ?></td>
        <td><a class="btn sm alt" href="expenses.php?month=<?= e(urlencode($month)) ?>&edit=<?= (int)$r['id'] ?>#form">Edit</a>
          <form method="post" action="expenses.php?month=<?= e(urlencode($month)) ?>" onsubmit="return confirm('Remove this expense?')">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="void"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($month) ?>">
            <button class="btn sm red">Remove</button></form></td>
      </tr>
      <?php endwhile; ?>
    </table></div>
    <?php if (!$shown): ?><div class="empty" style="margin-top:14px">No expenses match. Add one above, or change the filters.</div><?php endif; ?>
    <?php if ($count > 500): ?><p class="muted">Showing the latest 500 of <?= $count ?>. Narrow the filter, or download the CSV for the full list.</p><?php endif; ?>
  </div>
</div>
</body></html>
