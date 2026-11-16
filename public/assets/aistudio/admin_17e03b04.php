<?php
require __DIR__ . '/lib.php';
// ---- filter helper (built in, no extra file needed) ----
// Shared by admin.php and export.php: turns ?q=&status=&type=&college= into a safe SQL WHERE.
function build_filters(array $g): array {
    $status  = in_array($g['status'] ?? '', ['pending', 'approved', 'revoked'], true) ? $g['status'] : 'all';
    $type    = in_array($g['type'] ?? '', ['Course', 'Value-added', 'Internship'], true) ? $g['type'] : '';
    $college = trim($g['college'] ?? '');
    $q       = trim($g['q'] ?? '');
    $where = []; $types = ''; $params = [];
    if ($status !== 'all') { $where[] = 'c.status=?';    $types .= 's'; $params[] = $status; }
    if ($type !== '')      { $where[] = 'c.cert_type=?'; $types .= 's'; $params[] = $type; }
    if ($college !== '')   { $where[] = 'c.college=?';   $types .= 's'; $params[] = $college; }
    if ($q !== '') {
        $where[] = '(c.student_name LIKE ? OR c.certificate_id LIKE ? OR c.course LIKE ? OR c.college LIKE ?)';
        $like = '%' . $q . '%'; $types .= 'ssss'; array_push($params, $like, $like, $like, $like);
    }
    return ['status' => $status, 'type' => $type, 'college' => $college, 'q' => $q,
            'where' => $where ? ' WHERE ' . implode(' AND ', $where) : '', 'types' => $types, 'params' => $params];
}
require_admin();
$db = dbc();

// ---------- filters, search, pages ----------
$f = build_filters($_GET);
$status = $f['status'];
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$cnt = $db->prepare('SELECT COUNT(*) FROM certificates c' . $f['where']);
if ($f['params']) $cnt->bind_param($f['types'], ...$f['params']);
$cnt->execute();
$total = (int)$cnt->get_result()->fetch_row()[0];
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = 'SELECT c.*, s.phone, s.email, s.email_sent_at, s.wa_sent_at, s.send_error,
               (SELECT COUNT(*) FROM verify_attempts v WHERE v.certificate_id=c.certificate_id AND v.success=1) AS scans
        FROM certificates c LEFT JOIN student_contacts s ON s.certificate_id=c.certificate_id'
     . $f['where'] . ' ORDER BY c.id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
$st = $db->prepare($sql);
if ($f['params']) $st->bind_param($f['types'], ...$f['params']);
$st->execute();
$list = $st->get_result();

// ---------- summary numbers ----------
$counts = ['pending' => 0, 'approved' => 0, 'revoked' => 0];
$q = $db->query('SELECT status, COUNT(*) n FROM certificates GROUP BY status');
while ($r = $q->fetch_assoc()) $counts[$r['status']] = (int)$r['n'];
$allCount = array_sum($counts);
$sentCount = (int)$db->query('SELECT COUNT(*) FROM student_contacts WHERE email_sent_at IS NOT NULL')->fetch_row()[0];
$scans30 = (int)$db->query('SELECT COUNT(*) FROM verify_attempts WHERE success=1 AND created_at > (NOW() - INTERVAL 30 DAY)')->fetch_row()[0];
$unsent = (int)$db->query("SELECT COUNT(*) FROM certificates c JOIN student_contacts s ON s.certificate_id=c.certificate_id WHERE c.status='approved' AND s.send_error IS NULL AND s.email IS NOT NULL AND s.email<>'' AND s.email_sent_at IS NULL")->fetch_row()[0];
$failedN = (int)$db->query("SELECT COUNT(*) FROM student_contacts WHERE send_error IS NOT NULL")->fetch_row()[0];
$colleges = [];
$cq = $db->query('SELECT DISTINCT college FROM certificates ORDER BY college');
while ($r = $cq->fetch_row()) $colleges[] = $r[0];

function qs(array $over = []): string {
    $p = array_merge($_GET, $over);
    foreach ($p as $k => $v) { if ($v === '' || $v === null) unset($p[$k]); }
    return 'admin.php' . ($p ? '?' . http_build_query($p) : '');
}

page_head('Certificates - Admin'); top_bar();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2a5b;--blue:#1f6feb;--soft:#e8f0fe;--line:#e3e8f0;--text:#1c2433;--muted:#6b7587;
  --green:#12805c;--greenbg:#dcf5ea;--amber:#a76200;--amberbg:#fff1d6;--red:#c0272d;--redbg:#fde4e4;--ease:cubic-bezier(.22,.8,.3,1)}
body{background:#f2f5fa}
.ax{max-width:1280px;margin:0 auto;padding:24px 20px 80px;font-family:'Inter','Segoe UI',system-ui,sans-serif;color:var(--text);font-size:15px;line-height:1.5}
.ax *{font-family:inherit}
@keyframes axRise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@keyframes axDrift{to{background-position:100% 50%}}
@keyframes axPulse{0%,100%{box-shadow:0 0 0 0 rgba(31,111,235,.35)}50%{box-shadow:0 0 0 9px rgba(31,111,235,0)}}

/* hero */
.ax-hero{position:relative;overflow:hidden;border-radius:20px;padding:30px 34px;color:#fff;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:18px;align-items:center;justify-content:space-between;
  background:linear-gradient(120deg,#0b2a5b,#123a7d 50%,#1f6feb);background-size:200% 200%;animation:axRise .6s var(--ease) both,axDrift 14s ease-in-out infinite alternate}
.ax-hero::before{content:"";position:absolute;right:-60px;top:-90px;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.08)}
.ax-hero::after{content:"";position:absolute;left:40%;bottom:-120px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.06)}
.ax-hero h1{margin:0 0 4px;font-size:28px;font-weight:800;letter-spacing:-.02em;position:relative}
.ax-hero p{margin:0;opacity:.86;position:relative;max-width:520px}
.ax-hero .acts{display:flex;flex-wrap:wrap;gap:10px;position:relative}
.ax-hero .acts a{background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.28);padding:10px 16px;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px;transition:all .2s var(--ease);backdrop-filter:blur(4px)}
.ax-hero .acts a:hover{background:#fff;color:var(--navy);transform:translateY(-2px)}

/* stats */
.ax-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px}
.ax-stat{background:#fff;border:1px solid var(--line);border-left:5px solid var(--blue);border-radius:16px;padding:16px 20px;animation:axRise .6s var(--ease) both;transition:transform .25s var(--ease),box-shadow .25s}
.ax-stat:hover{transform:translateY(-4px);box-shadow:0 12px 26px rgba(11,42,91,.12)}
.ax-stat b{display:block;font-size:30px;line-height:1.1;color:var(--navy);font-variant-numeric:tabular-nums}
.ax-stat span{font-size:13px;color:var(--muted)}
.ax-stat:nth-child(1){animation-delay:.05s}.ax-stat:nth-child(2){animation-delay:.1s;border-left-color:#e8a317}.ax-stat:nth-child(3){animation-delay:.15s;border-left-color:#1aa377}
.ax-stat:nth-child(4){animation-delay:.2s;border-left-color:#7a5af8}.ax-stat:nth-child(5){animation-delay:.25s;border-left-color:#0ea5c6}

/* cards */
.ax-card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:24px 26px;margin-bottom:20px;animation:axRise .6s .1s var(--ease) both}
.ax-h{display:flex;align-items:center;gap:12px;margin:0 0 6px}
.ax-h i{flex:none;width:34px;height:34px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;font:800 15px Inter,sans-serif;font-style:normal}
.ax-h h2{margin:0;font-size:21px;font-weight:800;color:var(--navy);letter-spacing:-.01em}
.ax-sub{color:var(--muted);margin:0 0 16px;font-size:14px}
.ax-sub b{color:var(--navy)}

/* buttons (scoped override of the shared .btn) */
.ax .btn{display:inline-flex;align-items:center;gap:6px;border:1px solid transparent;border-radius:11px;padding:10px 18px;font:700 14px Inter,sans-serif;background:var(--blue);color:#fff;cursor:pointer;text-decoration:none;transition:transform .15s var(--ease),box-shadow .2s,background .15s}
.ax .btn:hover{background:#1558c0;transform:translateY(-1px);box-shadow:0 8px 18px rgba(31,111,235,.28)}
.ax .btn.alt{background:var(--soft);color:var(--navy)}.ax .btn.alt:hover{background:#d3e3fd;box-shadow:none}
.ax .btn.red{background:#fff;color:var(--red);border-color:#f0b9bb}.ax .btn.red:hover{background:var(--redbg);box-shadow:none}
.ax .btn.sm{padding:6px 11px;font-size:12px;border-radius:9px}
.ax .btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}

/* upload */
.ax-drop{display:flex;flex-wrap:wrap;gap:16px;align-items:center;border:2px dashed #b9c8e3;border-radius:16px;padding:22px;background:linear-gradient(180deg,#f7faff,#eef4ff);cursor:pointer;transition:all .25s var(--ease)}
.ax-drop:hover,.ax-drop.over{border-color:var(--blue);background:#e8f0fe;transform:scale(1.005)}
.ax-drop .ic{flex:none;width:54px;height:54px;border-radius:16px;display:grid;place-items:center;background:#fff;color:var(--blue);box-shadow:0 6px 16px rgba(31,111,235,.18)}
.ax-drop .ic svg{width:26px;height:26px}
.ax-drop .tx{flex:1;min-width:200px}.ax-drop b{display:block;color:var(--navy);font-size:16px}.ax-drop span{color:var(--muted);font-size:13px}
.ax-drop input[type=file]{display:none}
.ax-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:14px}
.ax-row label{color:var(--muted);font-size:14px}
.ax select,.ax input[type=search]{padding:10px 12px;border:1px solid var(--line);border-radius:11px;background:#fff;font:14px Inter,sans-serif;color:var(--text);outline:none;transition:border-color .2s,box-shadow .2s}
.ax select:focus,.ax input[type=search]:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(31,111,235,.15)}
#preview{margin-top:14px}
#preview:not(:empty){background:#f7faff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;font-size:14px}

/* tabs + filters */
.ax-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.ax-tab{border:1px solid var(--line);background:#fff;color:var(--navy);padding:8px 16px;border-radius:999px;font-weight:700;font-size:14px;text-decoration:none;transition:all .2s var(--ease)}
.ax-tab:hover{border-color:#b9c8e3;transform:translateY(-1px)}
.ax-tab.on{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:0 6px 16px rgba(11,42,91,.25)}
.ax-tab em{font-style:normal;opacity:.7;margin-left:4px}
.ax-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 16px}
.ax-filters input[type=search]{min-width:260px;flex:1;max-width:380px}

/* auto send panel */
.ax-auto{display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;border-radius:16px;padding:18px 20px;margin:0 0 16px;color:#fff;background:linear-gradient(120deg,#123a7d,#1f6feb)}
.ax-auto b{font-size:16px}.ax-auto .n{display:block;opacity:.85;font-size:13px;margin-top:2px}
.ax-auto .acts{display:flex;flex-wrap:wrap;gap:10px}
.ax-auto .btn{background:#fff;color:var(--navy)}.ax-auto .btn:hover{background:#eaf2ff}
.ax-auto .btn.alt{background:rgba(255,255,255,.18);color:#fff;border-color:rgba(255,255,255,.35)}.ax-auto .btn.alt:hover{background:rgba(255,255,255,.3)}
.ax-auto .btn.red{background:#fff0f0;color:var(--red)}
#sendStatus{flex-basis:100%;font-size:14px;margin:0}
#sendStatus:empty{display:none}
.ax-auto #sendStatus .ok{color:#b6f2d6;font-weight:700}.ax-auto #sendStatus .err{color:#ffd0d0;font-weight:700}

/* bulk */
.ax-bulk{display:none;position:sticky;top:8px;z-index:20;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 14px;padding:12px 16px;border-radius:14px;background:var(--navy);color:#fff;box-shadow:0 14px 30px rgba(11,42,91,.3)}
.ax-bulk.on{display:flex;animation:axRise .3s var(--ease) both}
.ax-bulk .btn{background:#fff;color:var(--navy)}.ax-bulk .btn.red{background:#fff0f0;color:var(--red)}

/* table */
.ax-count{color:var(--muted);font-size:14px;margin:0 0 10px}
.ax-tbl{border:1px solid var(--line);border-radius:16px;overflow:hidden;overflow-x:auto;background:#fff}
.ax-tbl table{width:100%;border-collapse:collapse;min-width:1080px;font-size:13px}
.ax-tbl th{background:#f1f5fb;color:var(--navy);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:12px 12px;white-space:nowrap}
.ax-tbl td{padding:13px 12px;border-top:1px solid var(--line);vertical-align:top}
.ax-tbl tbody tr{transition:background .2s}.ax-tbl tbody tr:hover td{background:#f6f9ff}
.ax-tbl input[type=checkbox]{width:17px;height:17px;accent-color:var(--blue);cursor:pointer}
.ax-id{font:600 12px ui-monospace,Menlo,Consolas,monospace;color:#55657f;background:#f1f5fb;border-radius:6px;padding:3px 8px;white-space:nowrap}
.ax-name{display:flex;gap:10px;align-items:center;font-weight:700;font-size:14px}
.ax-av{flex:none;width:34px;height:34px;border-radius:11px;display:grid;place-items:center;font-weight:800;font-size:14px;color:hsl(var(--h) 55% 26%);background:hsl(var(--h) 80% 91%)}
.ax .tag{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;font-size:12px;font-weight:700}
.ax .tag::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.ax .tag.pending{background:var(--amberbg);color:var(--amber)}.ax .tag.approved{background:var(--greenbg);color:var(--green)}.ax .tag.revoked{background:var(--redbg);color:var(--red)}
.ax-sent{display:grid;gap:3px;font-size:12px;color:var(--green);font-weight:600}.ax-sent .none{color:#9aa5b8;font-weight:500}
.ax .err{color:var(--red)}.ax .ok{color:var(--green)}
.ax-act{display:flex;flex-wrap:wrap;gap:5px;max-width:330px}
.ax-act form{display:inline}
.ax-scan{display:inline-block;min-width:28px;text-align:center;font-weight:700;color:var(--navy);background:#eef4ff;border-radius:8px;padding:2px 8px}
.ax-empty td{text-align:center;padding:44px 12px!important;color:var(--muted)}
.ax-empty b{display:block;font-size:17px;color:var(--navy)}

.ax-pager{display:flex;gap:10px;align-items:center;justify-content:center;margin:18px 0 0}

@media (max-width:980px){.ax-stats{grid-template-columns:repeat(2,1fr)}.ax-hero{padding:22px}.ax-hero h1{font-size:23px}}
@media (prefers-reduced-motion:reduce){.ax *,.ax *::before,.ax *::after{animation:none!important;transition:none!important}}
</style>

<div class="ax">

  <section class="ax-hero">
    <div>
      <h1>Certificate dashboard</h1>
      <p>Upload a student sheet, approve the certificates, and send them to the students.</p>
    </div>
    <div class="acts">
      <a href="#upload">⬆ Upload sheet</a>
      <a href="#approve">✔ Approve &amp; send</a>
      <a href="history.php">🕘 History</a>
    </div>
  </section>

  <section class="ax-stats">
    <div class="ax-stat"><b data-n="<?= $allCount ?>"><?= $allCount ?></b><span>Total certificates</span></div>
    <div class="ax-stat"><b data-n="<?= $counts['pending'] ?>"><?= $counts['pending'] ?></b><span>Pending approval</span></div>
    <div class="ax-stat"><b data-n="<?= $counts['approved'] ?>"><?= $counts['approved'] ?></b><span>Approved</span></div>
    <div class="ax-stat"><b data-n="<?= $sentCount ?>"><?= $sentCount ?></b><span>Emails sent</span></div>
    <div class="ax-stat"><b data-n="<?= $scans30 ?>"><?= $scans30 ?></b><span>Verified in last 30 days</span></div>
  </section>

  <section class="ax-card" id="upload">
    <div class="ax-h"><i>1</i><h2>Upload student Excel sheet</h2></div>
    <p class="ax-sub">Columns (first row): <b>Student Name, College, Course, Type, Duration, Start Date, End Date, Phone, Email</b>.
      Type = Course / Value-added / Internship. Duration is optional (worked out from the dates). Uploaded certificates stay <b>pending</b> until you approve them.</p>

    <label class="ax-drop" id="drop" for="file">
      <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg></span>
      <span class="tx"><b id="dropTitle">Drop your Excel or CSV file here</b><span id="dropSub">or click to choose a file (.xlsx, .xls, .csv)</span></span>
      <input type="file" id="file" accept=".xlsx,.xls,.csv">
    </label>

    <div class="ax-row">
      <label for="defType">Default type (if the Type column is empty):</label>
      <select id="defType"><option>Course</option><option>Value-added</option><option>Internship</option></select>
    </div>
    <div id="preview" class="muted"></div>
    <div class="ax-row"><button class="btn" id="sendBtn" disabled>Save certificates</button> <span id="result"></span></div>
  </section>

  <section class="ax-card" id="approve">
    <div class="ax-h"><i>2</i><h2>Approve &amp; send</h2></div>
    <p class="ax-sub">Review the certificates, approve them, then send them to the students by email.</p>

    <div class="ax-tabs">
      <a class="ax-tab <?= $status==='all'?'on':'' ?>" href="<?= e(qs(['status' => '', 'page' => ''])) ?>">All<em><?= $allCount ?></em></a>
      <a class="ax-tab <?= $status==='pending'?'on':'' ?>" href="<?= e(qs(['status' => 'pending', 'page' => ''])) ?>">Pending<em><?= $counts['pending'] ?></em></a>
      <a class="ax-tab <?= $status==='approved'?'on':'' ?>" href="<?= e(qs(['status' => 'approved', 'page' => ''])) ?>">Approved<em><?= $counts['approved'] ?></em></a>
      <a class="ax-tab <?= $status==='revoked'?'on':'' ?>" href="<?= e(qs(['status' => 'revoked', 'page' => ''])) ?>">Revoked<em><?= $counts['revoked'] ?></em></a>
    </div>

    <form class="ax-filters" method="get" action="admin.php">
      <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search name, ID, course, college">
      <select name="college"><option value="">All colleges</option>
        <?php foreach ($colleges as $c): ?><option<?= $f['college'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
      <select name="type"><option value="">All types</option>
        <?php foreach (['Course', 'Value-added', 'Internship'] as $t): ?><option<?= $f['type'] === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
      <button class="btn sm">Search</button>
      <a class="btn sm alt" href="admin.php">Clear</a>
      <a class="btn sm alt" href="export.php?<?= e(http_build_query(array_filter(['status' => $status === 'all' ? '' : $status, 'type' => $f['type'], 'college' => $f['college'], 'q' => $f['q']]))) ?>">⬇ Export to Excel</a>
    </form>

    <div class="ax-auto">
      <div><b>Automatic sending</b><span class="n"><?= $unsent ?> approved certificate(s) with email waiting to be sent.</span></div>
      <div class="acts">
        <button class="btn" onclick="sendAll(true)">Approve all pending &amp; send</button>
        <button class="btn alt" onclick="sendAll(false)">Send to all approved (not sent yet)</button>
        <?php if ($failedN): ?><button class="btn red" onclick="retryFailed()">Retry <?= $failedN ?> failed</button><?php endif; ?>
      </div>
      <div id="sendStatus"></div>
    </div>

    <div class="ax-bulk" id="bulkBar">
      <b><span id="selCount">0</span> selected</b>
      <button class="btn sm" onclick="bulk('approve')">Approve</button>
      <button class="btn sm" onclick="bulk('approve_send')">Approve &amp; send</button>
      <button class="btn sm" onclick="bulk('revoke')">Revoke</button>
      <button class="btn sm" onclick="bulk('resend')">Mark as not sent</button>
      <button class="btn sm red" onclick="bulk('delete')">Delete</button>
    </div>

    <p class="ax-count"><?= $total ?> result(s)<?= $f['q'] !== '' ? ' for “' . e($f['q']) . '”' : '' ?> — page <?= $page ?> of <?= $pages ?></p>
    <div class="ax-tbl"><table>
      <thead><tr><th><input type="checkbox" id="chkAll" title="Select all on this page"></th><th>Certificate ID</th><th>Student</th><th>College</th><th>Programme</th><th>Type</th><th>Period</th><th>Status</th><th>Sent</th><th>Scans</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if ($list->num_rows === 0): ?>
        <tr class="ax-empty"><td colspan="11"><b>No certificates found</b>Upload a sheet above, or clear the search and filters.</td></tr>
      <?php endif; ?>
      <?php while ($r = $list->fetch_assoc()):
          $url = verify_url($r['qr_token']);
          $msg = "Hello {$r['student_name']}, your NEWTRI Technologies certificate for {$r['course']} is ready. View / download it here: $url";
          $ph = last10($r['phone'] ?? '');
          $hue = hexdec(substr(md5(strtolower(trim($r['student_name']))), 0, 3)) % 360;
          $ini = mb_strtoupper(mb_substr(trim($r['student_name']), 0, 1)) ?: '?';
      ?>
      <tr>
        <td><input type="checkbox" class="rowchk" value="<?= e($r['certificate_id']) ?>"></td>
        <td><span class="ax-id"><?= e($r['certificate_id']) ?></span></td>
        <td><div class="ax-name"><span class="ax-av" style="--h:<?= $hue ?>"><?= e($ini) ?></span><a href="history.php?q=<?= e(urlencode($r['student_name'])) ?>" style="color:inherit;text-decoration:none" title="See history"><?= e($r['student_name']) ?></a></div></td>
        <td><?= e($r['college']) ?></td>
        <td><?= e($r['course']) ?></td>
        <td><?= e($r['cert_type']) ?></td>
        <td><?= e($r['duration']) ?><br><span class="muted"><?= e(fmt_date($r['start_date'])) ?> – <?= e(fmt_date($r['end_date'])) ?></span></td>
        <td><span class="tag <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><div class="ax-sent"><?= $r['email_sent_at'] ? '<span>✉ Email ✓</span>' : '' ?><?= $r['wa_sent_at'] ? '<span>💬 WhatsApp ✓</span>' : '' ?><?= (!$r['email_sent_at'] && !$r['wa_sent_at'] && !$r['send_error']) ? '<span class="none">—</span>' : '' ?><?= $r['send_error'] ? '<span class="err">' . e($r['send_error']) . '</span>' : '' ?></div></td>
        <td><span class="ax-scan"><?= (int)$r['scans'] ?></span></td>
        <td>
          <div class="ax-act">
          <?php foreach ([['approve', 'Approve', ''], ['revoke', 'Revoke', 'red'], ['delete', 'Delete', 'red']] as [$do, $label, $cls]):
              if (($do === 'approve' && $r['status'] === 'approved') || ($do === 'revoke' && $r['status'] === 'revoked')) continue; ?>
            <form method="post" action="action.php"<?php if ($do === 'delete'): ?> onsubmit="return confirm(<?= e(json_encode('Delete ' . $r['student_name'] . ' (' . $r['certificate_id'] . ') permanently?' . "\n\n" . 'If this certificate was already sent, its QR code and link will stop working. Use Revoke instead if you only want to cancel it.')) ?>)"<?php endif; ?>>
              <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="do" value="<?= $do ?>">
              <input type="hidden" name="id" value="<?= e($r['certificate_id']) ?>"><input type="hidden" name="back" value="<?= e($status) ?>">
              <button class="btn sm <?= $cls ?>"><?= $label ?></button></form>
          <?php endforeach; ?>
          <a class="btn sm alt" href="edit.php?id=<?= e(urlencode($r['certificate_id'])) ?>">Edit</a>
          <?php if ($r['status'] === 'approved'): ?>
            <a class="btn sm alt" target="_blank" href="<?= e($url) ?>">View</a>
            <button type="button" class="btn sm alt" onclick="copyLink(this, <?= e(json_encode($url)) ?>)">Copy link</button>
            <?php if ($ph): ?><a class="btn sm alt" target="_blank" href="https://wa.me/91<?= $ph ?>?text=<?= rawurlencode($msg) ?>">WhatsApp</a><?php endif; ?>
            <?php if (!empty($r['email'])): ?><a class="btn sm alt" href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Your NEWTRI Technologies certificate') ?>&body=<?= rawurlencode($msg) ?>">Email</a><?php endif; ?>
          <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table></div>

    <?php if ($pages > 1): ?>
    <div class="ax-pager">
      <?php if ($page > 1): ?><a class="btn sm alt" href="<?= e(qs(['page' => $page - 1])) ?>">← Previous</a><?php endif; ?>
      <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn sm alt" href="<?= e(qs(['page' => $page + 1])) ?>">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const CSRF = <?= json_encode(csrf()) ?>;
let rows = [];
const h = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const key = k => String(k).toLowerCase().replace(/[^a-z0-9]/g, '');
const pick = (o, names) => { for (const n of names) if (o[n] !== undefined && String(o[n]).trim() !== '') return String(o[n]).trim(); return ''; };
function nd(v) {                       // any date -> YYYY-MM-DD
  const s = String(v || '').trim();
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
  const m = s.match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/);
  if (!m) return '';
  const y = m[3].length === 2 ? '20' + m[3] : m[3];
  return `${y}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
}

/* count-up numbers in the top cards */
document.querySelectorAll('[data-n]').forEach(el => {
  const to = +el.dataset.n, t0 = performance.now();
  if (matchMedia('(prefers-reduced-motion: reduce)').matches || !to) return;
  (function tick(t) { const p = Math.min(1, (t - t0) / 900); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick); })(t0);
});

/* drag & drop onto the upload box */
const drop = document.getElementById('drop'), fileInput = document.getElementById('file');
['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
drop.addEventListener('drop', e => {
  const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (!f) return;
  if (!/\.(xlsx|xls|csv)$/i.test(f.name)) { document.getElementById('preview').innerHTML = '<span class="err">Please choose an .xlsx, .xls or .csv file.</span>'; return; }
  const dt = new DataTransfer(); dt.items.add(f); fileInput.files = dt.files;
  fileInput.dispatchEvent(new Event('change'));
});

fileInput.addEventListener('change', async e => {
  const f = e.target.files[0]; if (!f) return;
  document.getElementById('dropTitle').textContent = f.name;
  document.getElementById('dropSub').textContent = 'Click to choose a different file';
  const wb = XLSX.read(await f.arrayBuffer(), { type: 'array' });
  const raw = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { raw: false, dateNF: 'yyyy-mm-dd', defval: '' });
  rows = raw.map(r => {
    const o = {}; for (const k in r) o[key(k)] = r[k];
    return { name: pick(o, ['studentname', 'name']), college: pick(o, ['college', 'collegename']), course: pick(o, ['course', 'programme', 'program', 'role']),
             type: pick(o, ['type', 'certificatetype']), duration: pick(o, ['duration']), start: nd(pick(o, ['startdate', 'start', 'from'])),
             end: nd(pick(o, ['enddate', 'end', 'to'])), phone: pick(o, ['phone', 'mobile', 'mobilenumber']), email: pick(o, ['email', 'emailid']) };
  }).filter(r => r.name && r.name.toLowerCase() !== 'example student');
  const bad = rows.filter(r => !r.college || !r.course || !r.start || !r.end).length;
  document.getElementById('preview').innerHTML = rows.length
    ? `<b>${rows.length}</b> student(s) found. First: ${h(rows[0].name)} — ${h(rows[0].course)} (${h(rows[0].start)} to ${h(rows[0].end)})`
      + (bad ? `<br><span class="err">${bad} row(s) have a missing college, course or date and will be skipped.</span>` : '')
    : 'No student rows found. Check the column names.';
  document.getElementById('sendBtn').disabled = !rows.length;
});
document.getElementById('sendBtn').addEventListener('click', async () => {
  const btn = document.getElementById('sendBtn'), out = document.getElementById('result');
  btn.disabled = true; out.textContent = 'Saving…';
  try {
    const res = await fetch('upload.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, defaultType: document.getElementById('defType').value, rows }) });
    const j = await res.json();
    if (j.error) { out.innerHTML = '<span class="err">' + h(j.error) + '</span>'; btn.disabled = false; return; }
    out.innerHTML = `<span class="ok">Saved ${j.inserted}, skipped ${j.skipped} duplicate(s).</span>` +
      (j.errors.length ? '<br><span class="err">' + j.errors.map(h).join('<br>') + '</span>' : '');
    if (j.inserted) setTimeout(() => location.href = 'admin.php?status=pending', 1500); else btn.disabled = false;
  } catch (err) { out.innerHTML = '<span class="err">Upload failed.</span>'; btn.disabled = false; }
});

async function post(url, obj) {
  const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ csrf: CSRF }, obj)) });
  return r.json();
}
async function runSend(ids) {
  const st = document.getElementById('sendStatus');
  let sent = 0, failed = 0;
  while (true) {
    st.textContent = `Sending… ${sent} sent, ${failed} failed (please keep this page open)`;
    const j = await post('send.php', { mode: 'send', ids: ids || [] });
    if (j.error) { st.innerHTML = '<span class="err">' + h(j.error) + '</span>'; return; }
    sent += j.sent; failed += j.failed;
    if (j.remaining <= 0 || (j.sent + j.failed) === 0) break;
  }
  st.innerHTML = `<span class="ok">Done: ${sent} sent.</span>` + (failed ? ` <span class="err">${failed} failed - see the Sent column.</span>` : '');
  setTimeout(() => location.reload(), 3000);
}
async function sendAll(approveFirst) {
  if (!confirm(approveFirst ? 'Approve ALL pending certificates and send them to the students now?' : 'Send to all approved students who have not received it yet?')) return;
  if (approveFirst) { const fd = new FormData(); fd.append('csrf', CSRF); fd.append('do', 'approve_all'); await fetch('action.php', { method: 'POST', body: fd }); }
  runSend(null);
}
async function retryFailed() { await post('send.php', { mode: 'retry' }); sendAll(false); }

// ---------- tick boxes + bulk actions ----------
const chks = () => [...document.querySelectorAll('.rowchk')];
const picked = () => chks().filter(c => c.checked).map(c => c.value);
function refreshSel() {
  const n = picked().length;
  document.getElementById('selCount').textContent = n;
  document.getElementById('bulkBar').classList.toggle('on', n > 0);
}
document.addEventListener('change', e => {
  if (e.target.id === 'chkAll') chks().forEach(c => c.checked = e.target.checked);
  if (e.target.id === 'chkAll' || e.target.classList.contains('rowchk')) refreshSel();
});
async function bulk(act) {
  const ids = picked();
  if (!ids.length) { alert('Tick at least one row first.'); return; }
  const words = { approve: 'Approve', approve_send: 'Approve and send', revoke: 'Revoke', resend: 'Mark as not sent', delete: 'DELETE permanently' };
  if (!confirm(`${words[act]} ${ids.length} selected certificate(s)?` + (act === 'delete' ? '\n\nThis cannot be undone. Links of certificates already sent will stop working.' : ''))) return;
  if (act === 'approve_send') {
    const a = await post('bulk.php', { do: 'approve', ids });
    if (a.error) { alert(a.error); return; }
    return runSend(ids);
  }
  const j = await post('bulk.php', { do: act, ids });
  if (j.error) { alert(j.error); return; }
  location.reload();
}
function copyLink(btn, url) {
  const done = () => { const t = btn.textContent; btn.textContent = 'Copied ✓'; setTimeout(() => btn.textContent = t, 1500); };
  if (navigator.clipboard) navigator.clipboard.writeText(url).then(done); else { prompt('Copy this link:', url); }
}
</script>
</body></html>