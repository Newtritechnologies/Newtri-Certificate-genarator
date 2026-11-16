import React, { useMemo, useState } from 'react';
import {
  AlertCircle,
  CheckCircle2,
  Copy,
  Download,
  ExternalLink,
  Mail,
  MessageCircle,
  Play,
  Send,
  X,
} from 'lucide-react';
import { CombinedCertificate, PortalSettings } from '../types';
import {
  buildStudentWhatsAppMessage,
  buildVerifyLink,
  buildWhatsAppDirectUrl,
  downloadCsv,
  fmtDate,
  last10,
} from '../utils/format';
import { updateContactDelivery } from '../services/certService';
import {
  sendCertificateEmail,
  sendCertificateWhatsAppApi,
  ServerCapabilities,
} from '../services/deliveryService';

interface BulkDispatchModalProps {
  targets: CombinedCertificate[];
  initialTab: 'email' | 'whatsapp';
  settings: PortalSettings;
  capabilities: ServerCapabilities;
  adminEmail: string;
  onClose: () => void;
  onNotify: (msg: string) => void;
}

interface ItemResult {
  status: 'pending' | 'sending' | 'sent' | 'failed' | 'skipped';
  message?: string;
}

export const BulkDispatchModal: React.FC<BulkDispatchModalProps> = ({
  targets,
  initialTab,
  settings,
  capabilities,
  adminEmail,
  onClose,
  onNotify,
}) => {
  const [tab, setTab] = useState<'email' | 'whatsapp'>(initialTab);
  const [includeAlreadySent, setIncludeAlreadySent] = useState<boolean>(targets.length === 1);
  const [running, setRunning] = useState<boolean>(false);
  const [results, setResults] = useState<Record<string, ItemResult>>({});
  const [waCursor, setWaCursor] = useState<number>(0);
  const [testEmailStatus, setTestEmailStatus] = useState<string>('');

  const approvedTargets = useMemo(
    () => targets.filter((c) => c.status === 'approved'),
    [targets]
  );

  const emailQueue = useMemo(
    () =>
      approvedTargets.filter(
        (c) => Boolean(c.email) && (includeAlreadySent || !c.emailSentAt)
      ),
    [approvedTargets, includeAlreadySent]
  );

  const waQueue = useMemo(
    () =>
      approvedTargets.filter(
        (c) => Boolean(last10(c.phone)) && (includeAlreadySent || !c.waSentAt)
      ),
    [approvedTargets, includeAlreadySent]
  );

  const runEmailBatch = async () => {
    if (!emailQueue.length || running) return;
    setRunning(true);
    let sent = 0;
    let failed = 0;

    for (const cert of emailQueue) {
      setResults((prev) => ({
        ...prev,
        [cert.certificateId]: { status: 'sending', message: 'Sending...' },
      }));

      const link = buildVerifyLink(cert.qrToken, settings.publicBaseUrl);
      const res = await sendCertificateEmail(cert, link, settings, capabilities);

      if (res.ok) {
        await updateContactDelivery(cert.certificateId, {
          emailSentAt: new Date().toISOString(),
          sendError: '',
        });
        sent++;
        setResults((prev) => ({
          ...prev,
          [cert.certificateId]: { status: 'sent', message: `Sent via ${res.via.toUpperCase()}` },
        }));
      } else {
        const errMsg = res.error || 'Delivery failed';
        await updateContactDelivery(cert.certificateId, {
          sendError: `Email: ${errMsg}`.slice(0, 220),
        });
        failed++;
        setResults((prev) => ({
          ...prev,
          [cert.certificateId]: { status: 'failed', message: errMsg },
        }));
      }

      // Gentle 250ms pacing to prevent rate limiting across 50+ emails
      await new Promise((r) => setTimeout(r, 250));
    }

    setRunning(false);
    onNotify(`Email batch finished: ${sent} sent${failed ? `, ${failed} failed` : ''}.`);
  };

  const handleTestEmail = async () => {
    if (!approvedTargets.length || !adminEmail) return;
    const sample = approvedTargets[0];
    setTestEmailStatus(`Sending test to ${adminEmail}...`);
    const link = buildVerifyLink(sample.qrToken, settings.publicBaseUrl);
    const res = await sendCertificateEmail(
      { ...sample, email: adminEmail },
      link,
      settings,
      capabilities
    );
    if (res.ok) {
      setTestEmailStatus(
        `Test sent to ${adminEmail} via ${res.via.toUpperCase()}. Check your inbox (and spam).`
      );
    } else {
      setTestEmailStatus(`Test failed: ${res.error}`);
    }
  };

  const runWaCloudBatch = async () => {
    if (!waQueue.length || running || !capabilities.waConfigured) return;
    setRunning(true);
    let sent = 0;
    let failed = 0;

    for (const cert of waQueue) {
      setResults((prev) => ({
        ...prev,
        [`wa_${cert.certificateId}`]: { status: 'sending', message: 'Sending...' },
      }));

      const link = buildVerifyLink(cert.qrToken, settings.publicBaseUrl);
      const res = await sendCertificateWhatsAppApi(cert, link);

      if (res.ok) {
        await updateContactDelivery(cert.certificateId, {
          waSentAt: new Date().toISOString(),
          sendError: '',
        });
        sent++;
        setResults((prev) => ({
          ...prev,
          [`wa_${cert.certificateId}`]: { status: 'sent', message: 'Delivered via Cloud API' },
        }));
      } else {
        const errMsg = res.error || 'WhatsApp API failed';
        await updateContactDelivery(cert.certificateId, {
          sendError: `WhatsApp: ${errMsg}`.slice(0, 220),
        });
        failed++;
        setResults((prev) => ({
          ...prev,
          [`wa_${cert.certificateId}`]: { status: 'failed', message: errMsg },
        }));
      }

      await new Promise((r) => setTimeout(r, 200));
    }

    setRunning(false);
    onNotify(`WhatsApp batch finished: ${sent} sent${failed ? `, ${failed} failed` : ''}.`);
  };

  const launchSingleWhatsApp = async (cert: CombinedCertificate, advanceCursor = false) => {
    const ph = last10(cert.phone);
    if (!ph) return;
    const link = buildVerifyLink(cert.qrToken, settings.publicBaseUrl);
    const msg = buildStudentWhatsAppMessage(
      cert.studentName,
      cert.course,
      link,
      settings.companyName
    );
    const url = buildWhatsAppDirectUrl(ph, msg);
    window.open(url, '_blank', 'noopener,noreferrer');

    await updateContactDelivery(cert.certificateId, {
      waSentAt: new Date().toISOString(),
      sendError: '',
    });
    setResults((prev) => ({
      ...prev,
      [`wa_${cert.certificateId}`]: { status: 'sent', message: 'Opened in WhatsApp' },
    }));

    if (advanceCursor) {
      setWaCursor((c) => Math.min(waQueue.length, c + 1));
    }
  };

  const buildGmailComposeUrl = (cert: CombinedCertificate) => {
    const link = buildVerifyLink(cert.qrToken, settings.publicBaseUrl);
    const period = `${fmtDate(cert.startDate)} to ${fmtDate(cert.endDate)}`;
    const subject = `Your ${settings.companyName} Certificate - ${cert.course}`;
    const body = `Dear ${cert.studentName},\n\nCongratulations! Your ${cert.certType} certificate for ${cert.course} (${period}) is ready.\n\nClick the link below anytime to view and download your PDF certificate:\n${link}\n\nAnyone can scan the QR code on your certificate to verify that it was issued by ${settings.companyName} (${settings.websiteUrl}).\n\nRegards,\n${settings.companyName}`;
    return `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(
      cert.email
    )}&su=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  };

  const handleExportWhatsAppCsv = () => {
    const rows = approvedTargets
      .filter((c) => Boolean(last10(c.phone)))
      .map((c) => {
        const ph = last10(c.phone);
        const link = buildVerifyLink(c.qrToken, settings.publicBaseUrl);
        const msg = buildStudentWhatsAppMessage(
          c.studentName,
          c.course,
          link,
          settings.companyName
        );
        return [
          c.certificateId,
          c.studentName,
          `91${ph}`,
          c.email,
          c.course,
          link,
          msg,
          buildWhatsAppDirectUrl(ph, msg),
        ];
      });

    downloadCsv(
      `newtri_bulk_individual_dispatch_${new Date().toISOString().slice(0, 10)}.csv`,
      [
        'Certificate ID',
        'Student Name',
        'WhatsApp Number',
        'Email',
        'Course',
        'Certificate Download Link',
        'Personalized Message',
        'Direct WhatsApp Link',
      ],
      rows
    );
    onNotify(`Exported ${rows.length} individual WhatsApp & Email dispatch rows.`);
  };

  const nextWaStudent = waQueue[waCursor];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
      <div className="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        {/* Header */}
        <div className="flex items-start justify-between border-b border-slate-200 px-6 py-4">
          <div>
            <h3 className="text-base font-bold text-slate-900">
              Individual &amp; Bulk Certificate Dispatch ({approvedTargets.length} Approved)
            </h3>
            <p className="mt-0.5 text-xs text-slate-600">
              Send personalized download links to 1 student or 50+ students at a time so each person receives their own certificate privately.
            </p>
          </div>
          <button
            type="button"
            disabled={running}
            onClick={onClose}
            className="rounded-lg p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-40 cursor-pointer"
            aria-label="Close"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Channel Switch + Resend Toggle */}
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-6 py-3">
          <div className="flex items-center gap-1 rounded-lg bg-slate-200/80 p-1">
            <button
              type="button"
              onClick={() => setTab('email')}
              className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold cursor-pointer ${
                tab === 'email' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'
              }`}
            >
              <Mail className="h-3.5 w-3.5" />
              <span>Email ({emailQueue.length})</span>
            </button>
            <button
              type="button"
              onClick={() => setTab('whatsapp')}
              className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold cursor-pointer ${
                tab === 'whatsapp' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'
              }`}
            >
              <MessageCircle className="h-3.5 w-3.5" />
              <span>WhatsApp ({waQueue.length})</span>
            </button>
          </div>

          <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
            <input
              type="checkbox"
              checked={includeAlreadySent}
              onChange={(e) => {
                setIncludeAlreadySent(e.target.checked);
                setWaCursor(0);
              }}
            />
            <span>Include students already marked sent (Resend)</span>
          </label>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          {!settings.publicBaseUrl.trim() && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-950">
              <strong>Student Link Base:</strong>{' '}
              <span className="font-mono">{buildVerifyLink('...', settings.publicBaseUrl)}</span> — Make sure your app is shared publicly (or set <span className="font-mono">https://newtritechnologies.com/certs</span> in <strong>Hosting &amp; Channels</strong>) so students never see a Google 403 error.
            </div>
          )}

          {approvedTargets.length === 0 ? (
            <div className="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-600">
              No approved certificates are selected. Approve the certificates first so their links and QR codes are active.
            </div>
          ) : tab === 'email' ? (
            <>
              {/* Email Engine Info + Actions */}
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div className="text-xs text-slate-700 space-y-1">
                  <div className="font-bold text-slate-900">
                    Active Email Engine:{' '}
                    {capabilities.smtpConfigured
                      ? `Gmail SMTP (${capabilities.smtpUser})`
                      : 'EmailJS (service_jxbxnpg / template_uwp1xya)'}
                  </div>
                  {!capabilities.smtpConfigured && (
                    <div className="text-slate-600">
                      Note: If EmailJS reports &ldquo;sent&rdquo; but students don&rsquo;t see the mail, verify your EmailJS template uses{' '}
                      <span className="font-mono">{'{{to_email}}'}</span> as the To Email, or add{' '}
                      <span className="font-mono">SMTP_USER</span> &amp; <span className="font-mono">SMTP_PASS</span> (Gmail App Password) in Secrets.
                    </div>
                  )}
                  {testEmailStatus && (
                    <div className="font-semibold text-[#0B2A5B]">{testEmailStatus}</div>
                  )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  {adminEmail && (
                    <button
                      type="button"
                      disabled={running}
                      onClick={handleTestEmail}
                      className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer"
                    >
                      Test Send to {adminEmail}
                    </button>
                  )}
                  <button
                    type="button"
                    disabled={running || emailQueue.length === 0}
                    onClick={runEmailBatch}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#0B2A5B] px-4 py-2 text-xs font-semibold text-white hover:bg-[#123a7d] disabled:opacity-50 cursor-pointer"
                  >
                    <Send className="h-3.5 w-3.5" />
                    <span>
                      {running
                        ? 'Sending Emails...'
                        : `Send Individually to ${emailQueue.length} Student(s)`}
                    </span>
                  </button>
                </div>
              </div>

              {/* Email Student Queue Table */}
              <div className="overflow-x-auto rounded-xl border border-slate-200">
                <table className="w-full border-collapse text-left text-xs">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50 text-slate-600">
                      <th className="px-3 py-2.5 font-semibold">Student</th>
                      <th className="px-3 py-2.5 font-semibold">Email</th>
                      <th className="px-3 py-2.5 font-semibold">Status</th>
                      <th className="px-3 py-2.5 font-semibold">Direct Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200">
                    {emailQueue.length === 0 ? (
                      <tr>
                        <td colSpan={4} className="px-4 py-8 text-center text-slate-500">
                          All selected students with emails have already been sent. Tick &ldquo;Include students already marked sent&rdquo; above to resend.
                        </td>
                      </tr>
                    ) : (
                      emailQueue.map((c) => {
                        const r = results[c.certificateId];
                        return (
                          <tr key={c.certificateId} className="hover:bg-slate-50">
                            <td className="px-3 py-2.5">
                              <div className="font-semibold text-slate-900">{c.studentName}</div>
                              <div className="font-mono text-[11px] text-slate-500">
                                {c.certificateId}
                              </div>
                            </td>
                            <td className="px-3 py-2.5 text-slate-700">{c.email}</td>
                            <td className="px-3 py-2.5">
                              {r?.status === 'sent' && (
                                <span className="inline-flex items-center gap-1 font-semibold text-emerald-700">
                                  <CheckCircle2 className="h-3.5 w-3.5" />
                                  {r.message}
                                </span>
                              )}
                              {r?.status === 'sending' && (
                                <span className="font-semibold text-[#1f6feb]">Sending...</span>
                              )}
                              {r?.status === 'failed' && (
                                <span className="inline-flex items-center gap-1 font-semibold text-red-700">
                                  <AlertCircle className="h-3.5 w-3.5" />
                                  {r.message}
                                </span>
                              )}
                              {!r && (
                                <span className="text-slate-500">
                                  {c.emailSentAt ? 'Previously sent' : 'Ready in queue'}
                                </span>
                              )}
                            </td>
                            <td className="px-3 py-2.5">
                              <a
                                href={buildGmailComposeUrl(c)}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50"
                              >
                                <ExternalLink className="h-3 w-3" />
                                Open Gmail
                              </a>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </>
          ) : (
            <>
              {/* WhatsApp Individual + 50+ Batch Tools */}
              <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 space-y-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="text-xs text-emerald-950 space-y-1 max-w-xl">
                    <div className="font-bold text-sm">
                      Send WhatsApp Individually to {waQueue.length} Student(s)
                    </div>
                    <p className="text-emerald-900">
                      {capabilities.waConfigured
                        ? 'WhatsApp Business Cloud API is configured—send to all 50+ students automatically with one click.'
                        : 'Use the Rapid Individual Queue below to send personal WhatsApp DMs one-by-one in seconds (each student gets only their own certificate link), or export the Bulk WhatsApp CSV for automated bulk sender tools.'}
                    </p>
                  </div>

                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={handleExportWhatsAppCsv}
                      className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-400 bg-white px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-50 cursor-pointer"
                    >
                      <Download className="h-3.5 w-3.5" />
                      <span>Bulk WhatsApp CSV</span>
                    </button>

                    {capabilities.waConfigured && (
                      <button
                        type="button"
                        disabled={running || waQueue.length === 0}
                        onClick={runWaCloudBatch}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50 cursor-pointer"
                      >
                        <Send className="h-3.5 w-3.5" />
                        <span>Send All {waQueue.length} via Cloud API</span>
                      </button>
                    )}
                  </div>
                </div>

                {/* Rapid Individual Launcher */}
                {waQueue.length > 0 && (
                  <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-white px-4 py-3">
                    {nextWaStudent ? (
                      <>
                        <div className="text-xs">
                          <span className="font-mono font-bold text-emerald-800">
                            Next ({waCursor + 1} / {waQueue.length}):
                          </span>{' '}
                          <span className="font-bold text-slate-900">
                            {nextWaStudent.studentName}
                          </span>{' '}
                          <span className="font-mono text-slate-600">
                            (+91 {last10(nextWaStudent.phone)})
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => void launchSingleWhatsApp(nextWaStudent, true)}
                          className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 cursor-pointer"
                        >
                          <Play className="h-3.5 w-3.5" />
                          <span>Send WhatsApp &amp; Advance Next</span>
                        </button>
                      </>
                    ) : (
                      <div className="flex w-full items-center justify-between text-xs font-semibold text-emerald-800">
                        <span>Finished launching all {waQueue.length} individual WhatsApp messages!</span>
                        <button
                          type="button"
                          onClick={() => setWaCursor(0)}
                          className="rounded border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs cursor-pointer"
                        >
                          Restart Queue
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>

              {/* WhatsApp Queue Table */}
              <div className="overflow-x-auto rounded-xl border border-slate-200">
                <table className="w-full border-collapse text-left text-xs">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50 text-slate-600">
                      <th className="px-3 py-2.5 font-semibold">#</th>
                      <th className="px-3 py-2.5 font-semibold">Student</th>
                      <th className="px-3 py-2.5 font-semibold">Phone</th>
                      <th className="px-3 py-2.5 font-semibold">Status</th>
                      <th className="px-3 py-2.5 font-semibold">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200">
                    {waQueue.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                          No selected students with valid 10-digit WhatsApp phone numbers waiting. Tick &ldquo;Include students already marked sent&rdquo; above to resend.
                        </td>
                      </tr>
                    ) : (
                      waQueue.map((c, idx) => {
                        const ph = last10(c.phone);
                        const link = buildVerifyLink(c.qrToken, settings.publicBaseUrl);
                        const msg = buildStudentWhatsAppMessage(
                          c.studentName,
                          c.course,
                          link,
                          settings.companyName
                        );
                        const r = results[`wa_${c.certificateId}`];
                        return (
                          <tr
                            key={c.certificateId}
                            className={idx === waCursor ? 'bg-emerald-50/50' : 'hover:bg-slate-50'}
                          >
                            <td className="px-3 py-2.5 font-mono text-slate-400">{idx + 1}</td>
                            <td className="px-3 py-2.5">
                              <div className="font-semibold text-slate-900">{c.studentName}</div>
                              <div className="font-mono text-[11px] text-slate-500">
                                {c.certificateId}
                              </div>
                            </td>
                            <td className="px-3 py-2.5 font-mono text-slate-700">+91 {ph}</td>
                            <td className="px-3 py-2.5">
                              {r?.status === 'sent' && (
                                <span className="inline-flex items-center gap-1 font-semibold text-emerald-700">
                                  <CheckCircle2 className="h-3.5 w-3.5" />
                                  {r.message}
                                </span>
                              )}
                              {r?.status === 'failed' && (
                                <span className="font-semibold text-red-700">{r.message}</span>
                              )}
                              {!r && (
                                <span className="text-slate-500">
                                  {c.waSentAt ? 'Previously sent' : 'Queued'}
                                </span>
                              )}
                            </td>
                            <td className="px-3 py-2.5">
                              <div className="flex items-center gap-1.5">
                                <button
                                  type="button"
                                  onClick={() => void launchSingleWhatsApp(c, false)}
                                  className="inline-flex items-center gap-1 rounded bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700 cursor-pointer"
                                >
                                  <MessageCircle className="h-3 w-3" />
                                  Send
                                </button>
                                <button
                                  type="button"
                                  onClick={() => {
                                    void navigator.clipboard.writeText(msg);
                                    onNotify(`Copied personal message for ${c.studentName}.`);
                                  }}
                                  className="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
                                >
                                  <Copy className="h-3 w-3" />
                                  Copy Msg
                                </button>
                              </div>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
};
