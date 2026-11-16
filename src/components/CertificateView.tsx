import React, { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import {
  CheckCircle2,
  Clock,
  Copy,
  Download,
  ExternalLink,
  Printer,
  Search,
  Share2,
  ShieldAlert,
  ShieldCheck,
} from 'lucide-react';
import { PortalSettings, PublicCertificate } from '../types';
import {
  buildStudentWhatsAppMessage,
  buildVerifyLink,
  fmtDate,
} from '../utils/format';
import { generateCertificatePdf } from '../utils/pdf';
import {
  fetchCertificateById,
  fetchCertificateByToken,
  recordPublicScan,
} from '../services/certService';

interface CertificateViewProps {
  initialToken: string;
  initialCertId?: string;
  settings: PortalSettings;
  onOpenAdmin: () => void;
  isAdmin: boolean;
}

export const CertificateView: React.FC<CertificateViewProps> = ({
  initialToken,
  initialCertId,
  settings,
  onOpenAdmin,
  isAdmin,
}) => {
  const [cert, setCert] = useState<PublicCertificate | null>(null);
  const [loading, setLoading] = useState<boolean>(Boolean(initialToken || initialCertId));
  const [error, setError] = useState<string>('');
  const [lookupId, setLookupId] = useState<string>(initialCertId || '');
  const [qrDataUrl, setQrDataUrl] = useState<string>('');
  const [copied, setCopied] = useState<boolean>(false);
  const [downloadingPdf, setDownloadingPdf] = useState<boolean>(false);

  const loadByToken = async (token: string) => {
    setLoading(true);
    setError('');
    try {
      const found = await fetchCertificateByToken(token);
      if (!found) {
        setCert(null);
        setError('No certificate record matches this verification link.');
      } else {
        setCert(found);
        if (found.status === 'approved') {
          void recordPublicScan(found.certificateId);
        }
      }
    } catch {
      setCert(null);
      setError('Unable to reach verification records right now. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const loadById = async (idInput: string) => {
    const clean = idInput.trim().toUpperCase();
    if (!clean) return;
    setLoading(true);
    setError('');
    try {
      const found = await fetchCertificateById(clean);
      if (!found) {
        setCert(null);
        setError(`No certificate found with ID "${clean}". Check the ID printed on the certificate.`);
      } else {
        setCert(found);
        const nextUrl = `${window.location.pathname}?t=${encodeURIComponent(found.qrToken)}`;
        window.history.replaceState(null, '', nextUrl);
        if (found.status === 'approved') {
          void recordPublicScan(found.certificateId);
        }
      }
    } catch {
      setCert(null);
      setError('Unable to verify that certificate ID right now.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (initialToken) {
      void loadByToken(initialToken);
    } else if (initialCertId) {
      void loadById(initialCertId);
    }
  }, [initialToken, initialCertId]);

  const verifyUrl = cert ? buildVerifyLink(cert.qrToken, settings.publicBaseUrl) : '';

  useEffect(() => {
    if (!verifyUrl) {
      setQrDataUrl('');
      return;
    }
    QRCode.toDataURL(verifyUrl, {
      width: 280,
      margin: 1,
      color: { dark: '#0B2A5B', light: '#FFFFFF' },
      errorCorrectionLevel: 'M',
    })
      .then(setQrDataUrl)
      .catch(() => setQrDataUrl(''));
  }, [verifyUrl]);

  const handleDownloadPdf = async () => {
    if (!cert || !verifyUrl) return;
    setDownloadingPdf(true);
    try {
      await generateCertificatePdf(cert, verifyUrl, settings.companyName, settings.websiteUrl);
    } finally {
      setDownloadingPdf(false);
    }
  };

  const handleCopy = async () => {
    if (!verifyUrl) return;
    try {
      await navigator.clipboard.writeText(verifyUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    } catch {
      // Fallback ignored if clipboard blocked
    }
  };

  const handleLookupSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    void loadById(lookupId);
  };

  const certHeading =
    cert?.certType === 'Internship'
      ? 'Certificate of Internship'
      : cert?.certType === 'Value-added'
        ? 'Value-Added Programme Certificate'
        : 'Certificate of Completion';

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      {/* Top Bar (3-zone contract) */}
      <header className="no-print border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-8 px-6 py-4">
          <a
            href="/"
            className="text-lg font-bold tracking-tight text-[#0B2A5B] whitespace-nowrap shrink-0"
          >
            {settings.companyName}
          </a>

          <nav className="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
            <a
              href="/?verify=1"
              className="hover:text-slate-900 transition-colors whitespace-nowrap shrink-0"
            >
              Verify Certificate
            </a>
            <a
              href={settings.websiteUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 hover:text-slate-900 transition-colors whitespace-nowrap shrink-0"
            >
              <span>Official Website</span>
              <ExternalLink className="h-3.5 w-3.5" />
            </a>
          </nav>

          <div className="flex items-center gap-3 shrink-0">
            <button
              type="button"
              onClick={onOpenAdmin}
              className="px-4 py-2 text-xs font-semibold text-white bg-[#0B2A5B] rounded-lg hover:bg-[#123a7d] transition-colors whitespace-nowrap shrink-0 cursor-pointer"
            >
              {isAdmin ? 'Admin Console' : 'Admin Sign In'}
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {/* Verification Search Bar */}
        <section className="no-print mb-8 rounded-xl border border-slate-200 bg-white p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <h1 className="text-xl font-bold text-[#0B2A5B]">
                Certificate Verification &amp; Download Portal
              </h1>
              <p className="mt-1 text-sm text-slate-600">
                Enter a Certificate ID (for example, <span className="font-mono text-slate-800">NT-2026-A7K9M2</span>) or scan the QR code on any {settings.companyName} certificate.
              </p>
            </div>
            <form onSubmit={handleLookupSubmit} className="flex w-full gap-2 md:w-auto">
              <div className="relative flex-1 md:w-64">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={lookupId}
                  onChange={(e) => setLookupId(e.target.value)}
                  placeholder="Enter Certificate ID"
                  aria-label="Certificate ID"
                  className="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 font-mono text-sm uppercase text-slate-900 focus:border-[#1f6feb] focus:outline-none focus:ring-2 focus:ring-[#1f6feb]/20"
                />
              </div>
              <button
                type="submit"
                className="rounded-lg bg-[#1f6feb] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1558c0] transition-colors whitespace-nowrap shrink-0 cursor-pointer"
              >
                Verify ID
              </button>
            </form>
          </div>
        </section>

        {loading && (
          <div className="rounded-xl border border-slate-200 bg-white p-12 text-center">
            <div className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-[#1f6feb]" />
            <p className="mt-3 text-sm font-medium text-slate-600">
              Verifying certificate record...
            </p>
          </div>
        )}

        {!loading && error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-red-900">
            <div className="flex items-start gap-3">
              <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-red-700" />
              <div>
                <h2 className="text-base font-bold">Certificate Not Verified</h2>
                <p className="mt-1 text-sm text-red-800">{error}</p>
              </div>
            </div>
          </div>
        )}

        {!loading && !error && !cert && (
          <div className="rounded-xl border border-slate-200 bg-white p-10 text-center">
            <ShieldCheck className="mx-auto h-10 w-10 text-[#0B2A5B]" />
            <h2 className="mt-3 text-lg font-bold text-slate-900">
              Verify an Official {settings.companyName} Credential
            </h2>
            <p className="mx-auto mt-2 max-w-xl text-sm text-slate-600">
              Students can open their personal link from WhatsApp or Email to download their PDF certificate anytime. Employers and institutions can scan the QR code on the certificate or search the Certificate ID above.
            </p>
          </div>
        )}

        {!loading && cert && (
          <>
            {/* Verification Status Banner */}
            {cert.status === 'approved' && (
              <section className="no-print mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-start gap-3">
                    <CheckCircle2 className="mt-0.5 h-6 w-6 shrink-0 text-emerald-700" />
                    <div>
                      <h2 className="text-base font-bold text-emerald-950">
                        Verified Official Certificate — Issued by {settings.companyName}
                      </h2>
                      <p className="mt-1 text-sm text-emerald-900">
                        This certificate confirms that <strong>{cert.studentName}</strong> ({cert.college}) attended and completed the <strong>{cert.certType}</strong> programme in <strong>{cert.course}</strong> ({cert.duration || `${fmtDate(cert.startDate)} – ${fmtDate(cert.endDate)}`}) conducted by{' '}
                        <a
                          href={settings.websiteUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="underline font-semibold"
                        >
                          {settings.companyName}
                        </a>
                        .
                      </p>
                      <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-emerald-800 font-mono tabular-nums">
                        <span>ID: {cert.certificateId}</span>
                        <span aria-hidden="true">·</span>
                        <span>Period: {fmtDate(cert.startDate)} – {fmtDate(cert.endDate)}</span>
                        <span aria-hidden="true">·</span>
                        <span>Issued: {fmtDate(cert.issueDate)}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </section>
            )}

            {cert.status === 'pending' && (
              <section className="no-print mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
                <div className="flex items-start gap-3">
                  <Clock className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                  <div>
                    <h2 className="text-base font-bold">Pending Administrative Approval</h2>
                    <p className="mt-1 text-sm text-amber-900">
                      This certificate record (<span className="font-mono">{cert.certificateId}</span>) has been uploaded but is not yet approved for public download or official verification.
                    </p>
                  </div>
                </div>
              </section>
            )}

            {cert.status === 'revoked' && (
              <section className="no-print mb-6 rounded-xl border border-red-200 bg-red-50 p-5 text-red-950">
                <div className="flex items-start gap-3">
                  <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-red-700" />
                  <div>
                    <h2 className="text-base font-bold">Certificate Revoked</h2>
                    <p className="mt-1 text-sm text-red-900">
                      Certificate <span className="font-mono">{cert.certificateId}</span> for <strong>{cert.studentName}</strong> was revoked by {settings.companyName} and is no longer valid.
                    </p>
                  </div>
                </div>
              </section>
            )}

            {/* Download & Share Action Strip */}
            {cert.status === 'approved' && (
              <div className="no-print mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4">
                <div className="text-sm text-slate-600">
                  Save your PDF certificate below—scanning its QR code always opens this verification record.
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                  <button
                    type="button"
                    onClick={handleDownloadPdf}
                    disabled={downloadingPdf}
                    className="inline-flex items-center gap-2 rounded-lg bg-[#0B2A5B] px-4 py-2 text-sm font-semibold text-white hover:bg-[#123a7d] transition-colors whitespace-nowrap shrink-0 cursor-pointer disabled:opacity-60"
                  >
                    <Download className="h-4 w-4" />
                    <span>{downloadingPdf ? 'Generating PDF...' : 'Download PDF'}</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors whitespace-nowrap shrink-0 cursor-pointer"
                  >
                    <Printer className="h-4 w-4" />
                    <span>Print</span>
                  </button>

                  <button
                    type="button"
                    onClick={handleCopy}
                    className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors whitespace-nowrap shrink-0 cursor-pointer"
                  >
                    <Copy className="h-4 w-4" />
                    <span>{copied ? 'Link Copied' : 'Copy Link'}</span>
                  </button>

                  <a
                    href={`https://wa.me/?text=${encodeURIComponent(
                      buildStudentWhatsAppMessage(
                        cert.studentName,
                        cert.course,
                        verifyUrl,
                        settings.companyName
                      )
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-sm font-medium text-emerald-900 hover:bg-emerald-100 transition-colors whitespace-nowrap shrink-0"
                  >
                    <Share2 className="h-4 w-4" />
                    <span>Share WhatsApp</span>
                  </a>
                </div>
              </div>
            )}

            {/* Formal Certificate Preview Sheet */}
            {cert.status === 'approved' && (
              <div className="print-only-sheet mx-auto rounded-xl border border-slate-200 bg-[#fdfcf8] p-4 shadow-sm sm:p-8">
                <div className="relative border-[3px] border-[#0B2A5B] p-2 sm:p-3">
                  <div className="border border-[#B88C38] px-6 py-8 sm:px-12 sm:py-12">
                    {/* Header */}
                    <div className="text-center">
                      <div className="text-xl font-extrabold tracking-[0.18em] text-[#0B2A5B] sm:text-2xl">
                        {settings.companyName.toUpperCase()}
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        Karangad, Kanyakumari District, Tamil Nadu · {settings.websiteUrl.replace(/\/$/, '')}
                      </p>
                      <div className="mx-auto my-5 h-px w-48 bg-slate-300" />
                      <h2
                        className="text-2xl font-bold text-[#123a7d] sm:text-3xl"
                        style={{ fontFamily: 'var(--font-serif)' }}
                      >
                        {certHeading}
                      </h2>
                    </div>

                    {/* Recipient & Program Body */}
                    <div className="mt-8 text-center">
                      <p className="text-sm text-slate-600">This is to proudly certify that</p>
                      <p
                        className="mt-3 text-3xl font-bold italic text-slate-900 sm:text-4xl"
                        style={{ fontFamily: 'var(--font-serif)' }}
                      >
                        {cert.studentName}
                      </p>
                      <div className="mx-auto mt-2 h-0.5 w-64 bg-[#B88C38]" />
                      <p className="mt-3 text-sm font-medium text-slate-700 sm:text-base">
                        of {cert.college}
                      </p>
                      <p className="mx-auto mt-5 max-w-2xl text-sm leading-relaxed text-slate-700 sm:text-base">
                        has successfully attended and completed the{' '}
                        <span className="font-semibold text-slate-900">{cert.certType}</span>{' '}
                        programme in{' '}
                        <span className="font-semibold text-slate-900">{cert.course}</span>
                        {cert.duration ? ` (${cert.duration})` : ''}, conducted by{' '}
                        <span className="font-semibold text-[#0B2A5B]">{settings.companyName}</span>{' '}
                        from <span className="font-medium">{fmtDate(cert.startDate)}</span> to{' '}
                        <span className="font-medium">{fmtDate(cert.endDate)}</span>.
                      </p>
                    </div>

                    {/* Footer: Metadata + QR Code + Signatory */}
                    <div className="mt-12 grid grid-cols-1 items-end gap-8 border-t border-slate-200 pt-8 sm:grid-cols-3">
                      <div className="text-left text-xs text-slate-600 space-y-1">
                        <div className="font-mono font-semibold text-[#0B2A5B]">
                          ID: {cert.certificateId}
                        </div>
                        <div>Issued: {fmtDate(cert.issueDate)}</div>
                        <div>
                          Verified at:{' '}
                          <span className="text-slate-800">{settings.websiteUrl.replace(/\/$/, '')}</span>
                        </div>
                      </div>

                      <div className="flex flex-col items-center">
                        <div className="rounded-lg border border-slate-200 bg-white p-2">
                          {qrDataUrl ? (
                            <img
                              src={qrDataUrl}
                              alt={`QR verification code for ${cert.certificateId}`}
                              className="h-24 w-24"
                            />
                          ) : (
                            <div className="flex h-24 w-24 items-center justify-center text-xs text-slate-400">
                              QR
                            </div>
                          )}
                        </div>
                        <span className="mt-2 text-[10px] font-bold tracking-wider text-[#0B2A5B]">
                          SCAN TO VERIFY AUTHENTICITY
                        </span>
                      </div>

                      <div className="text-center sm:text-right">
                        <div className="ml-auto w-44 border-b border-slate-400 pb-1 text-center">
                          <span
                            className="text-lg italic text-[#0B2A5B]"
                            style={{ fontFamily: 'var(--font-serif)' }}
                          >
                            NEWTRI Admin
                          </span>
                        </div>
                        <div className="mt-1.5 text-xs font-semibold text-slate-900">
                          Authorized Signatory
                        </div>
                        <div className="text-[11px] text-slate-500">{settings.companyName}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </>
        )}
      </main>
    </div>
  );
};
