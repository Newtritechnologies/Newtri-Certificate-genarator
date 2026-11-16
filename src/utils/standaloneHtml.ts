import firebaseConfig from '../../firebase-applet-config.json';
import { PortalSettings } from '../types';

export function downloadStandaloneVerifierHtml(settings: PortalSettings): void {
  const companyName = settings.companyName || 'NEWTRI Technologies';
  const websiteUrl = settings.websiteUrl || 'https://newtritechnologies.com/';

  const html = `<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>${escapeHtml(companyName)} — Certificate Verification & Download</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=JetBrains+Mono:wght@500;600&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet" />
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DM Sans', system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
    header { background: #fff; border-bottom: 1px solid #e2e8f0; }
    .top { max-width: 1080px; margin: 0 auto; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    .brand { font-weight: 800; font-size: 18px; color: #0B2A5B; text-decoration: none; }
    .site-link { font-size: 14px; font-weight: 600; color: #475569; text-decoration: none; }
    .site-link:hover { color: #0f172a; }
    main { max-width: 980px; margin: 28px auto; padding: 0 16px 64px; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; margin-bottom: 20px; }
    .search-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
    .search-form { display: flex; gap: 8px; flex: 1; max-width: 380px; }
    input[type="text"] { flex: 1; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 13px; text-transform: uppercase; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 8px; border: 1px solid transparent; font: 600 13px 'DM Sans', sans-serif; cursor: pointer; text-decoration: none; }
    .btn-primary { background: #0B2A5B; color: #fff; }
    .btn-blue { background: #1f6feb; color: #fff; }
    .btn-outline { background: #fff; color: #334155; border-color: #cbd5e1; }
    .btn-wa { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; }
    .banner-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #064e3b; border-radius: 12px; padding: 18px 20px; margin-bottom: 18px; }
    .banner-warn { background: #fffbeb; border: 1px solid #fde68a; color: #78350f; border-radius: 12px; padding: 18px 20px; margin-bottom: 18px; }
    .banner-err { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; border-radius: 12px; padding: 18px 20px; margin-bottom: 18px; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
    .sheet { background: #fdfcf8; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
    .frame-outer { border: 3px solid #0B2A5B; padding: 10px; }
    .frame-inner { border: 1px solid #B88C38; padding: 36px 28px; text-align: center; }
    .co { font-size: 22px; font-weight: 800; letter-spacing: 0.16em; color: #0B2A5B; }
    .co-sub { margin-top: 4px; font-size: 12px; color: #64748b; }
    .rule { width: 180px; height: 1px; background: #cbd5e1; margin: 18px auto; }
    .title { font-family: 'Playfair Display', Georgia, serif; font-size: 28px; font-weight: 700; color: #123a7d; margin: 0; }
    .student { font-family: 'Playfair Display', Georgia, serif; font-style: italic; font-size: 36px; font-weight: 700; color: #0f172a; margin: 12px 0 6px; }
    .name-rule { width: 240px; height: 2px; background: #B88C38; margin: 0 auto 12px; }
    .body-text { max-width: 660px; margin: 18px auto 0; font-size: 15px; line-height: 1.65; color: #334155; }
    .foot { margin-top: 40px; padding-top: 24px; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; align-items: end; }
    .foot-left { text-align: left; font-size: 12px; color: #475569; line-height: 1.7; }
    .mono { font-family: 'JetBrains Mono', monospace; }
    .qr-box { display: inline-block; padding: 8px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; }
    .qr-box img { display: block; width: 96px; height: 96px; }
    .qr-cap { margin-top: 6px; font-size: 10px; font-weight: 700; letter-spacing: 0.06em; color: #0B2A5B; }
    .sig { text-align: right; }
    .sig-line { display: inline-block; width: 170px; border-bottom: 1px solid #94a3b8; padding-bottom: 4px; font-family: 'Playfair Display', serif; font-style: italic; font-size: 18px; color: #0B2A5B; text-align: center; }
    @media (max-width: 680px) { .foot { grid-template-columns: 1fr; text-align: center; } .foot-left, .sig { text-align: center; } }
    @media print {
      @page { size: A4 landscape; margin: 8mm; }
      header, .no-print { display: none !important; }
      body { background: #fff; }
      .sheet { border: 0; padding: 0; }
    }
  </style>
</head>
<body>
  <header class="no-print">
    <div class="top">
      <a class="brand" href="?verify=1">${escapeHtml(companyName)}</a>
      <a class="site-link" href="${escapeHtml(websiteUrl)}" target="_blank" rel="noopener noreferrer">Official Website</a>
    </div>
  </header>

  <main>
    <div class="card no-print">
      <div class="search-row">
        <div>
          <h1 style="margin:0;font-size:19px;color:#0B2A5B">Certificate Verification &amp; Download</h1>
          <p style="margin:4px 0 0;font-size:13px;color:#64748b">Enter a Certificate ID or scan the QR code on any ${escapeHtml(companyName)} certificate.</p>
        </div>
        <form class="search-form" id="lookupForm">
          <input type="text" id="lookupInput" placeholder="NT-2026-XXXXXX" required />
          <button type="submit" class="btn btn-blue">Verify</button>
        </form>
      </div>
    </div>

    <div id="app"></div>
  </main>

  <script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/11.0.2/firebase-app.js';
    import { doc, getDoc, getFirestore } from 'https://www.gstatic.com/firebasejs/11.0.2/firebase-firestore.js';
    import QRCode from 'https://esm.sh/qrcode@1.5.4';
    import { jsPDF } from 'https://esm.sh/jspdf@2.5.2';

    const firebaseConfig = ${JSON.stringify({
      apiKey: firebaseConfig.apiKey,
      authDomain: firebaseConfig.authDomain,
      projectId: firebaseConfig.projectId,
      appId: firebaseConfig.appId,
    })};
    const DATABASE_ID = ${JSON.stringify(firebaseConfig.firestoreDatabaseId)};
    const COMPANY_NAME = ${JSON.stringify(companyName)};
    const WEBSITE_URL = ${JSON.stringify(websiteUrl)};

    const app = initializeApp(firebaseConfig);
    const db = getFirestore(app, DATABASE_ID);
    const root = document.getElementById('app');

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const fmtDate = (iso) => {
      const m = String(iso || '').match(/^(\\d{4})-(\\d{2})-(\\d{2})$/);
      if (!m) return iso || '';
      const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
      return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    };

    async function loadByToken(token) {
      const clean = String(token || '').trim().toLowerCase();
      if (!/^[a-f0-9]{16,64}$/.test(clean)) return null;
      const snap = await getDoc(doc(db, 'public_certificates', clean));
      return snap.exists() ? snap.data() : null;
    }

    async function loadById(certId) {
      const clean = String(certId || '').trim().toUpperCase();
      if (!/^[A-Z0-9-]{6,30}$/.test(clean)) return null;
      const idx = await getDoc(doc(db, 'cert_id_index', clean));
      if (!idx.exists() || !idx.data().qrToken) return null;
      return await loadByToken(idx.data().qrToken);
    }

    async function downloadPdf(cert, verifyUrl) {
      const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
      const w = pdf.internal.pageSize.getWidth();
      const h = pdf.internal.pageSize.getHeight();

      pdf.setFillColor(253, 252, 248); pdf.rect(0, 0, w, h, 'F');
      pdf.setDrawColor(11, 42, 91); pdf.setLineWidth(1.6); pdf.rect(10, 10, w - 20, h - 20);
      pdf.setDrawColor(184, 140, 56); pdf.setLineWidth(0.6); pdf.rect(14, 14, w - 28, h - 28);

      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(22); pdf.setTextColor(11, 42, 91);
      pdf.text(COMPANY_NAME.toUpperCase(), w / 2, 31, { align: 'center' });
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(9.5); pdf.setTextColor(85, 101, 127);
      pdf.text('Karangad, Kanyakumari District, Tamil Nadu  ·  ' + WEBSITE_URL.replace(/\\/$/, ''), w / 2, 37.5, { align: 'center' });
      pdf.setDrawColor(203, 213, 225); pdf.setLineWidth(0.35); pdf.line(68, 43, w - 68, 43);

      const heading = cert.certType === 'Internship' ? 'CERTIFICATE OF INTERNSHIP'
        : cert.certType === 'Value-added' ? 'VALUE-ADDED PROGRAMME CERTIFICATE' : 'CERTIFICATE OF COMPLETION';
      pdf.setFont('times', 'bold'); pdf.setFontSize(25); pdf.setTextColor(18, 58, 125);
      pdf.text(heading, w / 2, 57, { align: 'center' });

      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(11); pdf.setTextColor(71, 85, 105);
      pdf.text('This is to proudly certify that', w / 2, 70, { align: 'center' });

      pdf.setFont('times', 'bolditalic'); pdf.setFontSize(31); pdf.setTextColor(15, 23, 42);
      pdf.text(cert.studentName, w / 2, 86, { align: 'center', maxWidth: 230 });
      pdf.setDrawColor(184, 140, 56); pdf.setLineWidth(0.5); pdf.line(78, 90, w - 78, 90);

      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(12); pdf.setTextColor(51, 65, 85);
      pdf.text('of ' + cert.college, w / 2, 99, { align: 'center', maxWidth: 230 });

      const dur = cert.duration ? ' (' + cert.duration + ')' : '';
      const body = 'has successfully attended and completed the ' + cert.certType + ' programme in ' + cert.course + dur + ', conducted by ' + COMPANY_NAME + ' from ' + fmtDate(cert.startDate) + ' to ' + fmtDate(cert.endDate) + '.';
      pdf.setFontSize(12); pdf.setTextColor(30, 41, 59);
      pdf.text(pdf.splitTextToSize(body, 210), w / 2, 113, { align: 'center', lineHeightFactor: 1.55 });

      const qrData = await QRCode.toDataURL(verifyUrl, { width: 360, margin: 1, color: { dark: '#0B2A5B', light: '#FFFFFF' } });
      pdf.setFont('courier', 'bold'); pdf.setFontSize(10); pdf.setTextColor(11, 42, 91);
      pdf.text('Certificate ID: ' + cert.certificateId, 26, 162);
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(9.5); pdf.setTextColor(85, 101, 127);
      pdf.text('Issued on: ' + fmtDate(cert.issueDate), 26, 168.5);
      pdf.text('Status: Verified & Active', 26, 174.5);

      pdf.setFillColor(255, 255, 255); pdf.setDrawColor(203, 213, 225); pdf.roundedRect(w / 2 - 20, 143, 40, 40, 2, 2, 'FD');
      pdf.addImage(qrData, 'PNG', w / 2 - 17.5, 145.5, 35, 35);
      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(7.5); pdf.setTextColor(11, 42, 91);
      pdf.text('SCAN QR TO VERIFY AUTHENTICITY', w / 2, 188.5, { align: 'center' });

      pdf.setDrawColor(100, 116, 139); pdf.line(w - 86, 170, w - 26, 170);
      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(10.5); pdf.setTextColor(15, 23, 42);
      pdf.text('Authorized Signatory', w - 56, 176, { align: 'center' });
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(9); pdf.setTextColor(85, 101, 127);
      pdf.text(COMPANY_NAME, w - 56, 181.5, { align: 'center' });

      pdf.save(cert.certificateId + '_' + cert.studentName.replace(/[^\\w]+/g, '_') + '.pdf');
    }

    async function renderCert(cert) {
      if (!cert) {
        root.innerHTML = '<div class="banner-err"><b>Certificate Not Found</b><p style="margin:6px 0 0;font-size:14px">No certificate record matched that link or ID.</p></div>';
        return;
      }
      if (cert.status === 'revoked') {
        root.innerHTML = '<div class="banner-err"><b>Certificate Revoked</b><p style="margin:6px 0 0;font-size:14px">Certificate ' + esc(cert.certificateId) + ' for ' + esc(cert.studentName) + ' was revoked by ' + esc(COMPANY_NAME) + '.</p></div>';
        return;
      }
      if (cert.status !== 'approved') {
        root.innerHTML = '<div class="banner-warn"><b>Pending Approval</b><p style="margin:6px 0 0;font-size:14px">Certificate ' + esc(cert.certificateId) + ' is awaiting administrative approval.</p></div>';
        return;
      }

      const verifyUrl = window.location.origin + window.location.pathname + '?t=' + encodeURIComponent(cert.qrToken);
      const qrData = await QRCode.toDataURL(verifyUrl, { width: 260, margin: 1, color: { dark: '#0B2A5B', light: '#FFFFFF' } });
      const title = cert.certType === 'Internship' ? 'Certificate of Internship'
        : cert.certType === 'Value-added' ? 'Value-Added Programme Certificate' : 'Certificate of Completion';
      const waMsg = 'Hello ' + cert.studentName + ', your ' + COMPANY_NAME + ' certificate for ' + cert.course + ' is ready: ' + verifyUrl;

      root.innerHTML =
        '<div class="banner-ok no-print">' +
          '<b>Verified Official Certificate — Issued by ' + esc(COMPANY_NAME) + '</b>' +
          '<p style="margin:6px 0 0;font-size:14px">This certificate confirms that <b>' + esc(cert.studentName) + '</b> (' + esc(cert.college) + ') attended and completed <b>' + esc(cert.course) + '</b> (' + esc(cert.certType) + ') conducted by <a href="' + esc(WEBSITE_URL) + '" target="_blank" style="color:inherit;font-weight:700">' + esc(COMPANY_NAME) + '</a>.</p>' +
        '</div>' +
        '<div class="card no-print actions">' +
          '<span style="font-size:13px;color:#475569">Download your PDF certificate below—scanning its QR code always verifies this record.</span>' +
          '<div style="display:flex;flex-wrap:wrap;gap:8px">' +
            '<button class="btn btn-primary" id="dlBtn">Download PDF</button>' +
            '<button class="btn btn-outline" onclick="window.print()">Print</button>' +
            '<a class="btn btn-wa" target="_blank" href="https://wa.me/?text=' + encodeURIComponent(waMsg) + '">Share WhatsApp</a>' +
          '</div>' +
        '</div>' +
        '<div class="sheet"><div class="frame-outer"><div class="frame-inner">' +
          '<div class="co">' + esc(COMPANY_NAME.toUpperCase()) + '</div>' +
          '<div class="co-sub">Karangad, Kanyakumari District, Tamil Nadu · ' + esc(WEBSITE_URL.replace(/\\/$/, '')) + '</div>' +
          '<div class="rule"></div>' +
          '<h2 class="title">' + esc(title) + '</h2>' +
          '<p style="margin:24px 0 0;font-size:14px;color:#475569">This is to proudly certify that</p>' +
          '<div class="student">' + esc(cert.studentName) + '</div>' +
          '<div class="name-rule"></div>' +
          '<div style="font-size:15px;font-weight:600;color:#334155">of ' + esc(cert.college) + '</div>' +
          '<p class="body-text">has successfully attended and completed the <b>' + esc(cert.certType) + '</b> programme in <b>' + esc(cert.course) + '</b>' + (cert.duration ? ' (' + esc(cert.duration) + ')' : '') + ', conducted by <b style="color:#0B2A5B">' + esc(COMPANY_NAME) + '</b> from ' + esc(fmtDate(cert.startDate)) + ' to ' + esc(fmtDate(cert.endDate)) + '.</p>' +
          '<div class="foot">' +
            '<div class="foot-left"><div class="mono" style="font-weight:700;color:#0B2A5B">ID: ' + esc(cert.certificateId) + '</div><div>Issued: ' + esc(fmtDate(cert.issueDate)) + '</div></div>' +
            '<div><div class="qr-box"><img src="' + qrData + '" alt="QR verification" /></div><div class="qr-cap">SCAN TO VERIFY AUTHENTICITY</div></div>' +
            '<div class="sig"><div class="sig-line">NEWTRI Admin</div><div style="font-size:12px;font-weight:700;margin-top:4px">Authorized Signatory</div><div style="font-size:11px;color:#64748b">' + esc(COMPANY_NAME) + '</div></div>' +
          '</div>' +
        '</div></div></div>';

      document.getElementById('dlBtn').addEventListener('click', () => downloadPdf(cert, verifyUrl));
    }

    document.getElementById('lookupForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('lookupInput').value.trim();
      if (!id) return;
      root.innerHTML = '<div class="card">Verifying certificate...</div>';
      const cert = await loadById(id);
      if (cert) history.replaceState(null, '', '?t=' + encodeURIComponent(cert.qrToken));
      await renderCert(cert);
    });

    const params = new URLSearchParams(window.location.search);
    const t = params.get('t');
    const id = params.get('id');
    if (t) {
      root.innerHTML = '<div class="card">Loading certificate...</div>';
      renderCert(await loadByToken(t));
    } else if (id) {
      document.getElementById('lookupInput').value = id;
      root.innerHTML = '<div class="card">Verifying certificate...</div>';
      renderCert(await loadById(id));
    } else {
      root.innerHTML = '<div class="card" style="text-align:center;color:#475569">Enter a Certificate ID above or open your personal WhatsApp / Email certificate link.</div>';
    }
  </script>
</body>
</html>`;

  const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'index.html';
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 4000);
}

function escapeHtml(s: string): string {
  return String(s ?? '').replace(/[&<>"']/g, (c) => {
    const map: Record<string, string> = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    };
    return map[c] || c;
  });
}
