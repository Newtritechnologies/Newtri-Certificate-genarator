import emailjs from '@emailjs/browser';
import { CombinedCertificate, PortalSettings } from '../types';
import { fmtDate } from '../utils/format';

// Keys preserved from the attached email-config.js
const EMAILJS_PUBLIC_KEY = 'GGY-VSox7t9QJOy-k';
const EMAILJS_SERVICE_ID = 'service_jxbxnpg';
const EMAILJS_TEMPLATE_ID = 'template_uwp1xya';

export interface ServerCapabilities {
  smtpConfigured: boolean;
  smtpUser: string | null;
  waConfigured: boolean;
}

export async function fetchServerCapabilities(): Promise<ServerCapabilities> {
  try {
    const res = await fetch('/api/capabilities');
    if (!res.ok) return { smtpConfigured: false, smtpUser: null, waConfigured: false };
    return (await res.json()) as ServerCapabilities;
  } catch {
    return { smtpConfigured: false, smtpUser: null, waConfigured: false };
  }
}

export async function sendCertificateEmail(
  cert: CombinedCertificate,
  link: string,
  settings: PortalSettings,
  capabilities: ServerCapabilities
): Promise<{ ok: boolean; error?: string; via: 'smtp' | 'emailjs' }> {
  const period = `${fmtDate(cert.startDate)} to ${fmtDate(cert.endDate)}`;

  if (capabilities.smtpConfigured) {
    try {
      const res = await fetch('/api/send-email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          to: cert.email,
          studentName: cert.studentName,
          course: cert.course,
          certType: cert.certType,
          duration: cert.duration,
          period,
          link,
        }),
      });
      const json = (await res.json().catch(() => ({}))) as { ok?: boolean; error?: string };
      if (!res.ok || !json.ok) {
        return { ok: false, error: json.error || `HTTP ${res.status}`, via: 'smtp' };
      }
      return { ok: true, via: 'smtp' };
    } catch (err) {
      return {
        ok: false,
        error: err instanceof Error ? err.message : 'SMTP request failed',
        via: 'smtp',
      };
    }
  }

  try {
    await emailjs.send(
      EMAILJS_SERVICE_ID,
      EMAILJS_TEMPLATE_ID,
      {
        to_email: cert.email,
        email: cert.email,
        to_name: cert.studentName,
        student_name: cert.studentName,
        college: cert.college,
        course: cert.course,
        cert_type: cert.certType,
        duration: cert.duration,
        period,
        certificate_id: cert.certificateId,
        verify_url: link,
        link,
        company_name: settings.companyName,
        website_url: settings.websiteUrl,
      },
      { publicKey: EMAILJS_PUBLIC_KEY }
    );
    return { ok: true, via: 'emailjs' };
  } catch (err) {
    const message =
      typeof err === 'object' && err !== null && 'text' in err
        ? String((err as { text: unknown }).text)
        : err instanceof Error
          ? err.message
          : 'EmailJS delivery failed';
    return { ok: false, error: message.slice(0, 200), via: 'emailjs' };
  }
}

export async function sendCertificateWhatsAppApi(
  cert: CombinedCertificate,
  link: string
): Promise<{ ok: boolean; error?: string }> {
  try {
    const res = await fetch('/api/send-whatsapp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phone: cert.phone,
        studentName: cert.studentName,
        course: cert.course,
        link,
      }),
    });
    const json = (await res.json().catch(() => ({}))) as { ok?: boolean; error?: string };
    if (!res.ok || !json.ok) {
      return { ok: false, error: json.error || `HTTP ${res.status}` };
    }
    return { ok: true };
  } catch (err) {
    return {
      ok: false,
      error: err instanceof Error ? err.message : 'WhatsApp API failed',
    };
  }
}
