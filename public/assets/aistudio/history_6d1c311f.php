<?php
require __DIR__ . '/lib.php';
require_admin();
date_default_timezone_set('Asia/Kolkata');
$db = dbc();

/* One row per certificate, with its delivery details. Same tables the dashboard already uses. */
$rs = $db->query('SELECT c.id, c.certificate_id, c.student_name, c.college, c.course, c.cert_type, c.duration, c.start_date, c.end_date,
                         c.status, c.qr_token, s.email, s.phone, s.email_sent_at, s.wa_sent_at, s.send_error,
                         v.n AS scans, v.last_scan
                  FROM certificates c
                  LEFT JOIN student_contacts s ON s.certificate_id = c.certificate_id
                  LEFT JOIN (SELECT certificate_id, COUNT(*) AS n, MAX(created_at) AS last_scan FROM verify_attempts WHERE success = 1 GROUP BY certificate_id) v
                         ON v.certificate_id = c.certificate_id
                  ORDER BY c.id DESC LIMIT 3000');
$students = []; $tot = ['certs' => 0, 'approved' => 0, 'pending' => 0, 'revoked' => 0, 'delivered' => 0];
$fmt = fn($t) => $t ? date('d M Y, h:i A', strtotime($t)) : '';
while ($r = $rs->fetch_assoc()) {
    $k = substr(md5(strtolower(trim($r['student_name'])) . '|' . strtolower(trim($r['college']))), 0, 10);
    if (!isset($students[$k])) $students[$k] = ['k' => $k, 'name' => $r['student_name'], 'college' => $r['college'], 'phone' => '', 'mail' => '', 'certs' => []];
    if ($students[$k]['phone'] === '' && last10($r['phone'] ?? '')) $students[$k]['phone'] = last10($r['phone']);
    if ($students[$k]['mail'] === '' && !empty($r['email'])) $students[$k]['mail'] = $r['email'];
    $students[$k]['certs'][] = [
        'id' => $r['certificate_id'], 'course' => $r['course'], 'type' => $r['cert_type'], 'duration' => $r['duration'],
        'period' => fmt_date($r['start_date']) . ' – ' . fmt_date($r['end_date']), 'start' => (string)$r['start_date'],
        'status' => $r['status'], 'url' => $r['status'] === 'approved' ? verify_url($r['qr_token']) : '',
        'email' => $fmt($r['email_sent_at']), 'wa' => $fmt($r['wa_sent_at']), 'err' => (string)($r['send_error'] ?? ''),
        'scans' => (int)($r['scans'] ?? 0), 'lastScan' => $fmt($r['last_scan'] ?? null),
    ];
    $tot['certs']++;
    if (isset($tot[$r['status']])) $tot[$r['status']]++;
    if ($r['email_sent_at'] || $r['wa_sent_at']) $tot['delivered']++;
}

/* Full phone / email are NOT put in the page. The "Show contact" button asks for them here (admin only). */
if (isset($_GET['contact'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $s = $students[(string)$_GET['contact']] ?? null;
    echo json_encode($s ? ['phone' => $s['phone'], 'mail' => $s['mail']] : ['error' => 'Student not found'], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$list = [];
foreach ($students as $s) {
    $s['mp'] = $s['phone'] !== '' ? '••••••' . substr($s['phone'], -4) : '';
    $s['me'] = $s['mail'] !== '' ? preg_replace('/^(.).*(@.*)$/u', '$1•••$2', $s['mail']) : '';
    $s['hc'] = ($s['phone'] !== '' || $s['mail'] !== '');
    unset($s['phone'], $s['mail']);
    $list[] = $s;
}
$colleges = array_values(array_unique(array_map(fn($s) => $s['college'], $list)));
sort($colleges, SORT_NATURAL | SORT_FLAG_CASE);
$types = [];
foreach ($list as $s) foreach ($s['certs'] as $c) if ($c['type'] !== '') $types[$c['type']] = 1;
$types = array_keys($types); sort($types);

$initQ = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
page_head('Certificate history - Admin'); top_bar();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b2a5b;--navy2:#123a7d;--blue:#1f6feb;--soft:#e8f0fe;--bg:#f2f5fa;--card:#fff;--line:#e3e8f0;--text:#1c2433;--muted:#6b7587;
  --green:#12805c;--greenbg:#dcf5ea;--amber:#a76200;--amberbg:#fff1d6;--red:#c0272d;--redbg:#fde4e4;--ease:cubic-bezier(.22,.8,.3,1);
  }
.hx,.hx-drawer,.hx-toast{font-family:'Inter','Segoe UI',system-ui,sans-serif;color:var(--text);font-size:15px;line-height:1.5}
.hx{max-width:1280px;margin:0 auto;padding:24px 20px 80px}
body{background:#f2f5fa}
@keyframes hxRise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
@keyframes hxDrift{to{background-position:100% 50%}}

/* hero */
.hx-hero{position:relative;overflow:hidden;border-radius:20px;padding:34px 36px 38px;color:#fff;margin-bottom:22px;
  background:linear-gradient(120deg,#0b2a5b,#123a7d 50%,#1f6feb);background-size:200% 200%;animation:hxRise .6s var(--ease) both,hxDrift 14s ease-in-out infinite alternate}
.hx-hero::before{content:"";position:absolute;right:-60px;top:-80px;width:280px;height:280px;border-radius:50%;background:rgba(255,255,255,.08)}
.hx-hero::after{content:"";position:absolute;right:120px;bottom:-110px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.06)}
.hx-hero h1{margin:0 0 6px;font-size:30px;font-weight:800;letter-spacing:-.02em;position:relative}
.hx-hero p{margin:0 0 20px;opacity:.86;max-width:560px;position:relative}
.hx-search{position:relative;max-width:620px}
.hx-search input{width:100%;padding:15px 52px 15px 48px;border:0;border-radius:14px;font:inherit;font-size:16px;color:var(--text);background:#fff;box-shadow:0 10px 30px rgba(5,20,50,.28);outline:none}
.hx-search input:focus{box-shadow:0 10px 30px rgba(5,20,50,.28),0 0 0 4px rgba(255,255,255,.35)}
.hx-search svg{position:absolute;left:16px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:#6b7587}
.hx-search kbd{position:absolute;right:14px;top:50%;transform:translateY(-50%);font:600 12px Inter,sans-serif;color:#6b7587;background:#eef2f8;border-radius:6px;padding:3px 8px}

/* stats */
.hx-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}
.hx-stat{background:var(--card);border:1px solid var(--line);border-left:5px solid var(--blue);border-radius:16px;padding:16px 20px;animation:hxRise .6s var(--ease) both;transition:transform .25s var(--ease),box-shadow .25s}
.hx-stat:hover{transform:translateY(-4px);box-shadow:0 12px 26px rgba(11,42,91,.12)}
.hx-stat b{display:block;font-size:30px;line-height:1.1;color:var(--navy);font-variant-numeric:tabular-nums}
.hx-stat span{font-size:13px;color:var(--muted)}
.hx-stat:nth-child(1){animation-delay:.08s}.hx-stat:nth-child(2){animation-delay:.14s;border-left-color:#7a5af8}.hx-stat:nth-child(3){animation-delay:.2s;border-left-color:#1aa377}
.hx-stat:nth-child(4){animation-delay:.26s;border-left-color:#e8a317}.hx-stat:nth-child(5){animation-delay:.32s;border-left-color:#d9484e}

/* toolbar */
.hx-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:18px;animation:hxRise .6s .3s var(--ease) both}
.hx-chip{border:1px solid var(--line);background:#fff;color:var(--navy);padding:8px 16px;border-radius:999px;font:inherit;font-weight:600;font-size:14px;cursor:pointer;transition:all .2s var(--ease)}
.hx-chip:hover{border-color:#b9c8e3;transform:translateY(-1px)}
.hx-chip.on{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:0 6px 16px rgba(11,42,91,.25)}
.hx-bar select{padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font:inherit;font-size:14px;color:var(--text);max-width:230px}
.hx-sp{flex:1}
.hx-seg{display:flex;background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden}
.hx-seg button{border:0;background:transparent;padding:8px 14px;font:inherit;font-weight:600;font-size:14px;color:var(--muted);cursor:pointer}
.hx-seg button.on{background:var(--soft);color:var(--navy)}
.hx-count{color:var(--muted);font-size:14px;margin:0 0 12px}
.hx :focus-visible,.hx-drawer :focus-visible{outline:3px solid #7fb0ff;outline-offset:2px}

/* student cards */
.hx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(285px,1fr));gap:16px}
.hx-card{text-align:left;background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px;cursor:pointer;font:inherit;color:inherit;position:relative;overflow:hidden;
  animation:hxRise .55s var(--ease) both;animation-delay:calc(var(--i,0) * 45ms);transition:transform .28s var(--ease),box-shadow .28s,border-color .2s}
.hx-card::before{content:"";position:absolute;left:0;top:0;right:0;height:4px;background:linear-gradient(90deg,hsl(var(--h) 80% 55%),hsl(calc(var(--h) + 40) 80% 60%));transform:scaleX(0);transform-origin:left;transition:transform .4s var(--ease)}
.hx-card:hover,.hx-card:focus-visible{transform:translateY(-6px);box-shadow:0 16px 34px rgba(11,42,91,.14);border-color:#c6d6f3}
.hx-card:hover::before,.hx-card:focus-visible::before{transform:scaleX(1)}
.hx-top{display:flex;gap:13px;align-items:center}
.hx-av{flex:none;width:46px;height:46px;border-radius:14px;display:grid;place-items:center;font-weight:800;font-size:18px;color:hsl(var(--h) 55% 26%);background:hsl(var(--h) 80% 91%)}
.hx-card b{display:block;line-height:1.25;font-size:16px}.hx-card small{color:var(--muted);display:block;line-height:1.3}
.hx-chips{display:flex;flex-wrap:wrap;gap:6px;margin:14px 0 4px}
.hx-pill{font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:#f1f5fb;color:var(--navy)}
.hx-pill.approved{background:var(--greenbg);color:var(--green)}.hx-pill.pending{background:var(--amberbg);color:var(--amber)}.hx-pill.revoked{background:var(--redbg);color:var(--red)}
.hx-foot{display:flex;justify-content:space-between;align-items:center;margin-top:12px;font-size:13px;color:var(--muted)}
.hx-foot .ok{color:var(--green);font-weight:600}
.hx-foot .go{color:var(--blue);font-weight:700;opacity:0;transform:translateX(-6px);transition:all .25s var(--ease)}
.hx-card:hover .go,.hx-card:focus-visible .go{opacity:1;transform:none}

/* list view */
.hx-tbl{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;overflow-x:auto}
.hx-tbl table{width:100%;border-collapse:collapse;min-width:760px}
.hx-tbl th{background:#f1f5fb;color:var(--navy);font-size:13px;font-weight:700;text-align:left;padding:12px 16px;white-space:nowrap}
.hx-tbl td{padding:13px 16px;border-top:1px solid var(--line);vertical-align:middle}
.hx-tbl tr.row{cursor:pointer;transition:background .2s}.hx-tbl tr.row:hover td,.hx-tbl tr.row:focus-visible td{background:#f6f9ff}
.hx-tbl .nm{display:flex;gap:12px;align-items:center;font-weight:700}
.hx-tbl .av2{width:36px;height:36px;border-radius:11px;display:grid;place-items:center;font-weight:800;font-size:14px;color:hsl(var(--h) 55% 26%);background:hsl(var(--h) 80% 91%)}
.hx-empty{padding:50px 20px;text-align:center;color:var(--muted);background:#fff;border:2px dashed var(--line);border-radius:18px}
.hx-empty b{display:block;font-size:18px;color:var(--navy);margin-bottom:4px}
.hx-more{margin:14px 0 0;text-align:center;color:var(--muted);font-size:14px}

/* drawer */
.hx-shade{position:fixed;inset:0;background:rgba(11,26,52,.5);opacity:0;pointer-events:none;transition:opacity .3s;z-index:90;backdrop-filter:blur(2px)}
.hx-drawer{position:fixed;top:0;right:0;bottom:0;width:min(520px,100%);background:#f7f9fd;z-index:91;transform:translateX(104%);visibility:hidden;transition:transform .42s var(--ease),visibility 0s .42s;box-shadow:-20px 0 50px rgba(11,42,91,.22);overflow-y:auto}
body.hx-open .hx-shade{opacity:1;pointer-events:auto}body.hx-open .hx-drawer{transform:none;visibility:visible;transition:transform .42s var(--ease),visibility 0s}body.hx-open{overflow:hidden}
.hx-dh{position:relative;padding:28px 26px 22px;color:#fff;background:linear-gradient(135deg,#0b2a5b,#1f6feb)}
.hx-dh .av{width:56px;height:56px;border-radius:16px;display:grid;place-items:center;font-weight:800;font-size:22px;background:rgba(255,255,255,.2);margin-bottom:12px}
.hx-dh h2{margin:0;font-size:24px;font-weight:800;letter-spacing:-.01em}.hx-dh p{margin:2px 0 0;opacity:.85}
.hx-dh .x{position:absolute;top:16px;right:16px;width:36px;height:36px;border-radius:50%;border:0;background:rgba(255,255,255,.2);color:#fff;font-size:22px;cursor:pointer;transition:transform .3s var(--ease),background .2s}
.hx-dh .x:hover{transform:rotate(90deg);background:rgba(255,255,255,.35)}
.hx-mini{display:flex;gap:10px;margin-top:16px}.hx-mini div{flex:1;background:rgba(255,255,255,.14);border-radius:12px;padding:9px 12px}.hx-mini b{display:block;font-size:20px;line-height:1.1}.hx-mini span{font-size:12px;opacity:.85}
.hx-db{padding:22px 26px 36px}
.hx-contact{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px 14px;margin-bottom:20px;font-size:14px}
.hx-contact span{color:var(--muted)}.hx-contact b{font-weight:600}
.hx-btn{display:inline-block;border:1px solid transparent;border-radius:10px;padding:8px 14px;font:inherit;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;background:var(--blue);color:#fff;transition:transform .15s var(--ease),box-shadow .2s,background .15s}
.hx-btn:hover{background:#1558c0;transform:translateY(-1px);box-shadow:0 6px 14px rgba(31,111,235,.3)}
.hx-btn.alt{background:var(--soft);color:var(--navy)}.hx-btn.alt:hover{background:#d3e3fd;box-shadow:none}
.hx-dh2{font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin:0 0 12px}
.hx-tl{list-style:none;margin:0;padding:0 0 0 30px;position:relative}
.hx-tl::before{content:"";position:absolute;left:11px;top:8px;bottom:8px;width:2px;background:linear-gradient(var(--blue),#cfe0ff)}
.hx-tl li{position:relative;margin-bottom:16px;animation:hxRise .5s var(--ease) both;animation-delay:calc(var(--i,0) * 70ms + .15s)}
.hx-tl li::before{content:"";position:absolute;left:-26px;top:20px;width:14px;height:14px;border-radius:50%;background:#fff;border:3px solid var(--blue);box-shadow:0 0 0 4px #f7f9fd}
.hx-tl li.approved::before{border-color:#1aa377}.hx-tl li.pending::before{border-color:#e8a317}.hx-tl li.revoked::before{border-color:#d9484e}
.hx-ev{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px 18px;transition:box-shadow .25s,transform .25s var(--ease)}
.hx-ev:hover{box-shadow:0 10px 24px rgba(11,42,91,.1);transform:translateX(-3px)}
.hx-ev h3{margin:0 0 2px;font-size:17px;color:var(--navy);font-weight:700}
.hx-ev .meta{color:var(--muted);font-size:13px}.hx-ev .row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0 0}
.hx-ev .id{font:600 12px ui-monospace,Menlo,monospace;color:#55657f;background:#f1f5fb;border-radius:6px;padding:3px 8px}
.hx-dl{margin-top:12px;border-top:1px dashed var(--line);padding-top:10px;font-size:13px;display:grid;gap:4px}
.hx-dl .e{color:var(--green)}.hx-dl .n{color:var(--muted)}.hx-dl .bad{color:var(--red);font-weight:600}
.hx-df{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.hx-toast{position:fixed;left:50%;bottom:26px;transform:translate(-50%,30px);background:var(--navy);color:#fff;padding:10px 20px;border-radius:12px;font-weight:600;opacity:0;pointer-events:none;transition:all .3s var(--ease);z-index:99}
.hx-toast.show{opacity:1;transform:translate(-50%,0)}

@media (max-width:900px){.hx-stats{grid-template-columns:repeat(2,1fr)}.hx-hero{padding:24px}.hx-hero h1{font-size:24px}.hx-sp{display:none}}
@media (prefers-reduced-motion:reduce){.hx *,.hx *::before,.hx *::after{animation:none!important;transition:none!important}}
@media print{body *{visibility:hidden}.hx-drawer,.hx-drawer *{visibility:visible}.hx-drawer{position:absolute;left:0;top:0;width:100%;transform:none!important;box-shadow:none;overflow:visible}.hx-dh .x,.hx-df,.hx-btn{display:none!important}}
</style>

<div class="hx">
  <section class="hx-hero">
    <h1>Certificate history</h1>
    <p>Every student, every certificate, and when it was delivered. Search a name, college, course or certificate ID.</p>
    <div class="hx-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input id="q" type="search" placeholder="Search students, colleges, courses, IDs…" value="<?= e($initQ) ?>" autocomplete="off" aria-label="Search certificate history"><kbd>/</kbd>
    </div>
  </section>

  <section class="hx-stats">
    <div class="hx-stat"><b data-n="<?= count($list) ?>">0</b><span>Students</span></div>
    <div class="hx-stat"><b data-n="<?= $tot['certs'] ?>">0</b><span>Certificates issued</span></div>
    <div class="hx-stat"><b data-n="<?= $tot['approved'] ?>">0</b><span>Approved</span></div>
    <div class="hx-stat"><b data-n="<?= $tot['delivered'] ?>">0</b><span>Delivered to students</span></div>
    <div class="hx-stat"><b data-n="<?= $tot['revoked'] ?>">0</b><span>Revoked</span></div>
  </section>

  <section class="hx-bar">
    <button class="hx-chip on" data-st="all">All</button>
    <button class="hx-chip" data-st="approved">Approved</button>
    <button class="hx-chip" data-st="pending">Pending</button>
    <button class="hx-chip" data-st="revoked">Revoked</button>
    <select id="type" aria-label="Certificate type"><option value="all">All types</option><?php foreach ($types as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select>
    <select id="college" aria-label="College"><option value="all">All colleges</option><?php foreach ($colleges as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
    <select id="sort" aria-label="Sort"><option value="recent">Latest first</option><option value="most">Most certificates</option><option value="az">Name A–Z</option></select>
    <span class="hx-sp"></span>
    <div class="hx-seg" role="group" aria-label="View"><button id="vCards" class="on">Cards</button><button id="vList">List</button></div>
  </section>

  <p class="hx-count" id="count"></p>
  <div id="out"></div>
</div>

<div class="hx-shade" id="shade"></div>
<aside class="hx-drawer" id="drawer" role="dialog" aria-modal="true" aria-label="Student history"><div id="dbody"></div></aside>
<div class="hx-toast" id="toast" role="status"></div>

<script>
const STUDENTS = <?= json_encode($list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const SHOW_MAX = 400;
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const hue = k => parseInt(k.slice(0, 3), 16) % 360;
const initial = n => (String(n).trim()[0] || '?').toUpperCase();
const st = { q: <?= json_encode($initQ, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>, status: 'all', type: 'all', college: 'all', sort: 'recent', view: 'cards' };
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* count-up for the top numbers */
document.querySelectorAll('[data-n]').forEach(el => {
  const to = +el.dataset.n, t0 = performance.now();
  if (reduce || !to) { el.textContent = to; return; }
  (function tick(t) { const p = Math.min(1, (t - t0) / 900); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick); })(t0);
});

function visible() {
  const q = st.q.toLowerCase().trim();
  let out = STUDENTS.filter(s => {
    const certOk = s.certs.some(c => (st.status === 'all' || c.status === st.status) && (st.type === 'all' || c.type === st.type));
    if (!certOk) return false;
    if (st.college !== 'all' && s.college !== st.college) return false;
    if (!q) return true;
    return (s.name + ' ' + s.college).toLowerCase().includes(q) || s.certs.some(c => (c.course + ' ' + c.id).toLowerCase().includes(q));
  });
  if (st.sort === 'most') out = out.slice().sort((a, b) => b.certs.length - a.certs.length);
  else if (st.sort === 'az') out = out.slice().sort((a, b) => a.name.localeCompare(b.name));
  return out;
}
function mix(s) { const m = { approved: 0, pending: 0, revoked: 0 }; s.certs.forEach(c => { if (m[c.status] !== undefined) m[c.status]++; }); return m; }
function delivered(s) { return s.certs.filter(c => c.email || c.wa).length; }

function render() {
  const list = visible(), box = $('#out');
  $('#count').textContent = list.length + (list.length === 1 ? ' student' : ' students');
  if (!list.length) { box.className = ''; box.innerHTML = '<div class="hx-empty"><b>No students found</b>Try a different search or clear the filters.</div>'; return; }
  const more = list.length > SHOW_MAX ? `<p class="hx-more">Showing the first ${SHOW_MAX} of ${list.length}. Use the search or filters to narrow down.</p>` : '';
  if (st.view === 'cards') {
    box.className = 'hx-grid';
    box.innerHTML = list.slice(0, SHOW_MAX).map((s, i) => {
      const pills = s.certs.slice(0, 3).map(c => `<span class="hx-pill ${esc(c.status)}">${esc(c.course)}</span>`).join('') + (s.certs.length > 3 ? `<span class="hx-pill">+${s.certs.length - 3} more</span>` : '');
      const d = delivered(s);
      return `<article class="hx-card" tabindex="0" role="button" data-k="${esc(s.k)}" aria-label="Open history for ${esc(s.name)}" style="--h:${hue(s.k)};--i:${Math.min(i, 14)}"><div class="hx-top"><span class="hx-av">${esc(initial(s.name))}</span><div><b>${esc(s.name)}</b><small>${esc(s.college)}</small></div></div>
        <div class="hx-chips">${pills}</div><div class="hx-foot"><span>${s.certs.length} certificate${s.certs.length === 1 ? '' : 's'}${d ? ` · <span class="ok">✓ ${d} delivered</span>` : ''}</span><span class="go">View history →</span></div></article>`;
    }).join('') ;
  } else {
    box.className = 'hx-tbl';
    box.innerHTML = '<table><thead><tr><th>Student</th><th>College</th><th>Certificates</th><th>Status</th><th>Delivered</th></tr></thead><tbody>' +
      list.slice(0, SHOW_MAX).map(s => { const m = mix(s);
        return `<tr class="row" tabindex="0" role="button" data-k="${esc(s.k)}" style="--h:${hue(s.k)}"><td><div class="nm"><span class="av2">${esc(initial(s.name))}</span>${esc(s.name)}</div></td><td>${esc(s.college)}</td><td>${s.certs.length}</td>
        <td>${m.approved ? `<span class="hx-pill approved">${m.approved} approved</span> ` : ''}${m.pending ? `<span class="hx-pill pending">${m.pending} pending</span> ` : ''}${m.revoked ? `<span class="hx-pill revoked">${m.revoked} revoked</span>` : ''}</td><td>${delivered(s)} of ${s.certs.length}</td></tr>`; }).join('') + '</tbody></table>';
  }
  const old = $('#moreNote'); if (old) old.remove();
  if (more) box.insertAdjacentHTML('afterend', more.replace('<p ', '<p id="moreNote" '));
}

/* drawer */
let current = null, lastFocus = null;
function openStudent(k) {
  const s = STUDENTS.find(x => x.k === k); if (!s) return; current = s; lastFocus = document.activeElement;
  const m = mix(s), d = delivered(s);
  const items = s.certs.map((c, i) => {
    const ev = [];
    if (c.email) ev.push(`<div class="e">✉ Emailed · ${esc(c.email)}</div>`);
    if (c.wa) ev.push(`<div class="e">💬 WhatsApp · ${esc(c.wa)}</div>`);
    if (c.err) ev.push(`<div class="bad">⚠ ${esc(c.err)}</div>`);
    if (!ev.length) ev.push('<div class="n">Not sent to the student yet.</div>');
    if (c.scans) ev.push(`<div class="e">🔍 Verified ${c.scans} time${c.scans === 1 ? '' : 's'}${c.lastScan ? ' · last ' + esc(c.lastScan) : ''}</div>`);
    return `<li class="${esc(c.status)}" style="--i:${i}"><div class="hx-ev"><h3>${esc(c.course)}</h3><div class="meta">${esc(c.type)} · ${esc(c.duration)} · ${esc(c.period)}</div>
      <div class="row"><span class="hx-pill ${esc(c.status)}">${esc(c.status)}</span><span class="id">${esc(c.id)}</span>
      ${c.url ? `<a class="hx-btn" target="_blank" rel="noopener" href="${esc(c.url)}">View certificate</a><button class="hx-btn alt" data-copy="${esc(c.url)}">Copy link</button>` : ''}<a class="hx-btn alt" href="edit.php?id=${encodeURIComponent(c.id)}">Edit</a></div>
      <div class="hx-dl">${ev.join('')}</div></div></li>`;
  }).join('');
  $('#dbody').innerHTML = `<div class="hx-dh" style="--h:${hue(s.k)}"><button class="x" id="closeX" aria-label="Close">×</button><div class="av">${esc(initial(s.name))}</div><h2>${esc(s.name)}</h2><p>${esc(s.college)}</p>
    <div class="hx-mini"><div><b>${s.certs.length}</b><span>Certificates</span></div><div><b>${m.approved}</b><span>Approved</span></div><div><b>${d}</b><span>Delivered</span></div><div><b>${s.certs.reduce((a, c) => a + (c.scans || 0), 0)}</b><span>Verified</span></div></div></div>
    <div class="hx-db"><div class="hx-contact"><span>Phone</span><b id="ph">${esc(s.mp || '—')}</b><span>Email</span><b id="em">${esc(s.me || '—')}</b>
      ${s.hc ? '<button class="hx-btn alt" id="reveal">Show contact</button>' : ''}</div>
      <p class="hx-dh2">Certificate timeline</p><ul class="hx-tl">${items}</ul>
      <div class="hx-df"><button class="hx-btn" id="csv">Download CSV</button><button class="hx-btn alt" id="prt">Print history</button><a class="hx-btn alt" href="admin.php?q=${encodeURIComponent(s.name)}">Manage in dashboard</a></div></div>`;
  document.body.classList.add('hx-open');
  $('#drawer').scrollTop = 0;
  setTimeout(() => { const x = $('#closeX'); if (x) x.focus(); }, 60);
}
function closeDrawer() {
  if (!document.body.classList.contains('hx-open')) return;
  document.body.classList.remove('hx-open');
  if (lastFocus && document.contains(lastFocus)) lastFocus.focus();
}
function toast(m) { const t = $('#toast'); t.textContent = m; t.classList.add('show'); clearTimeout(t._); t._ = setTimeout(() => t.classList.remove('show'), 1800); }
/* stop Excel from running a cell that starts with = + - @ */
const csvSafe = v => { const s = String(v ?? ''); return /^[=+\-@\t\r]/.test(s) ? "'" + s : s; };

document.addEventListener('click', async e => {
  const card = e.target.closest('[data-k]'); if (card) { openStudent(card.dataset.k); return; }
  const cp = e.target.closest('[data-copy]'); if (cp) { navigator.clipboard.writeText(cp.dataset.copy).then(() => toast('Link copied'), () => toast('Could not copy')); return; }
  if (e.target.id === 'closeX' || e.target.id === 'shade') closeDrawer();
  if (e.target.id === 'reveal' && current) {
    const btn = e.target; btn.disabled = true; btn.textContent = 'Loading…';
    try {
      const r = await fetch('history.php?contact=' + encodeURIComponent(current.k), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const j = await r.json();
      if (j.error) throw new Error(j.error);
      $('#ph').textContent = j.phone ? '+91 ' + j.phone : '—';
      $('#em').textContent = j.mail || '—';
      btn.remove();
    } catch (err) { btn.disabled = false; btn.textContent = 'Show contact'; toast('Could not load contact (are you still logged in?)'); }
  }
  if (e.target.id === 'prt') window.print();
  if (e.target.id === 'csv' && current) {
    const q = v => '"' + csvSafe(v).replace(/"/g, '""') + '"';
    const rows = [['Student', 'College', 'Certificate ID', 'Programme', 'Type', 'Duration', 'Period', 'Status', 'Emailed', 'WhatsApp'].map(q).join(',')]
      .concat(current.certs.map(c => [current.name, current.college, c.id, c.course, c.type, c.duration, c.period, c.status, c.email, c.wa].map(q).join(',')));
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + rows.join('\r\n')], { type: 'text/csv' }));
    a.download = current.name.replace(/[^\w]+/g, '_') + '_certificates.csv'; a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  }
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeDrawer();
  if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[data-k][tabindex]')) { e.preventDefault(); openStudent(e.target.dataset.k); }
  if (e.key === '/' && !/INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) { e.preventDefault(); $('#q').focus(); }
});

/* filters */
$('#q').addEventListener('input', e => { st.q = e.target.value; render(); });
document.querySelectorAll('.hx-chip').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('.hx-chip').forEach(x => x.classList.toggle('on', x === b)); st.status = b.dataset.st; render();
}));
$('#type').addEventListener('change', e => { st.type = e.target.value; render(); });
$('#college').addEventListener('change', e => { st.college = e.target.value; render(); });
$('#sort').addEventListener('change', e => { st.sort = e.target.value; render(); });
$('#vCards').addEventListener('click', () => { st.view = 'cards'; $('#vCards').classList.add('on'); $('#vList').classList.remove('on'); render(); });
$('#vList').addEventListener('click', () => { st.view = 'list'; $('#vList').classList.add('on'); $('#vCards').classList.remove('on'); render(); });
render();
</script>
</body></html>