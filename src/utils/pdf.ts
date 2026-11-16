import { jsPDF } from 'jspdf';
import QRCode from 'qrcode';
import { PublicCertificate } from '../types';
import { fmtDate } from './format';

export async function generateCertificatePdf(
  cert: PublicCertificate,
  verifyUrl: string,
  companyName = 'NEWTRI Technologies',
  websiteUrl = 'https://newtritechnologies.com/'
): Promise<void> {
  const doc = new jsPDF({
    orientation: 'landscape',
    unit: 'mm',
    format: 'a4',
  });

  const w = doc.internal.pageSize.getWidth(); // 297mm
  const h = doc.internal.pageSize.getHeight(); // 210mm

  // Ivory parchment background
  doc.setFillColor(253, 252, 248);
  doc.rect(0, 0, w, h, 'F');

  // Outer deep navy frame
  doc.setDrawColor(11, 42, 91);
  doc.setLineWidth(1.6);
  doc.rect(10, 10, w - 20, h - 20);

  // Inner refined gold rule
  doc.setDrawColor(184, 140, 56);
  doc.setLineWidth(0.6);
  doc.rect(14, 14, w - 28, h - 28);

  // Corner geometric accents
  doc.setFillColor(11, 42, 91);
  const corners = [
    [10, 10],
    [w - 16, 10],
    [10, h - 16],
    [w - 16, h - 16],
  ];
  for (const [cx, cy] of corners) {
    doc.rect(cx, cy, 6, 6, 'F');
  }

  // Company Header
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(22);
  doc.setTextColor(11, 42, 91);
  doc.text(companyName.toUpperCase(), w / 2, 31, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9.5);
  doc.setTextColor(85, 101, 127);
  doc.text(
    `Karangad, Kanyakumari District, Tamil Nadu  ·  ${websiteUrl.replace(/\/$/, '')}`,
    w / 2,
    37.5,
    { align: 'center' }
  );

  // Thin divider line
  doc.setDrawColor(203, 213, 225);
  doc.setLineWidth(0.35);
  doc.line(68, 43, w - 68, 43);

  // Certificate heading
  const heading =
    cert.certType === 'Internship'
      ? 'CERTIFICATE OF INTERNSHIP'
      : cert.certType === 'Value-added'
        ? 'VALUE-ADDED PROGRAMME CERTIFICATE'
        : 'CERTIFICATE OF COMPLETION';

  doc.setFont('times', 'bold');
  doc.setFontSize(25);
  doc.setTextColor(18, 58, 125);
  doc.text(heading, w / 2, 57, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(11);
  doc.setTextColor(71, 85, 105);
  doc.text('This is to proudly certify that', w / 2, 70, { align: 'center' });

  // Student Name
  doc.setFont('times', 'bolditalic');
  doc.setFontSize(31);
  doc.setTextColor(15, 23, 42);
  doc.text(cert.studentName, w / 2, 86, { align: 'center', maxWidth: 230 });

  // Name underline
  doc.setDrawColor(184, 140, 56);
  doc.setLineWidth(0.5);
  doc.line(78, 90, w - 78, 90);

  // College
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(12);
  doc.setTextColor(51, 65, 85);
  doc.text(`of ${cert.college}`, w / 2, 99, { align: 'center', maxWidth: 230 });

  // Program attestation paragraph
  const typeLabel =
    cert.certType === 'Internship'
      ? 'internship programme'
      : cert.certType === 'Value-added'
        ? 'value-added programme'
        : 'training course';
  const durationClause = cert.duration ? ` (${cert.duration})` : '';
  const bodyText = `has successfully attended and completed the ${typeLabel} in ${cert.course}${durationClause}, conducted by ${companyName} from ${fmtDate(cert.startDate)} to ${fmtDate(cert.endDate)}.`;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(12);
  doc.setTextColor(30, 41, 59);
  const splitBody = doc.splitTextToSize(bodyText, 210);
  doc.text(splitBody, w / 2, 113, { align: 'center', lineHeightFactor: 1.55 });

  // QR Code generation
  const qrDataUrl = await QRCode.toDataURL(verifyUrl, {
    width: 360,
    margin: 1,
    color: { dark: '#0B2A5B', light: '#FFFFFF' },
    errorCorrectionLevel: 'M',
  });

  // Footer Zone (y = 148 to 188)
  // Left: Certificate ID & Issue Date
  doc.setFont('courier', 'bold');
  doc.setFontSize(10);
  doc.setTextColor(11, 42, 91);
  doc.text(`Certificate ID: ${cert.certificateId}`, 26, 162);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9.5);
  doc.setTextColor(85, 101, 127);
  doc.text(`Issued on: ${fmtDate(cert.issueDate)}`, 26, 168.5);
  doc.text(`Status: ${cert.status === 'approved' ? 'Verified & Active' : cert.status.toUpperCase()}`, 26, 174.5);

  // Center: Scannable QR Verification Box
  doc.setFillColor(255, 255, 255);
  doc.setDrawColor(203, 213, 225);
  doc.setLineWidth(0.4);
  doc.roundedRect(w / 2 - 20, 143, 40, 40, 2, 2, 'FD');
  doc.addImage(qrDataUrl, 'PNG', w / 2 - 17.5, 145.5, 35, 35);

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(7.5);
  doc.setTextColor(11, 42, 91);
  doc.text('SCAN QR TO VERIFY AUTHENTICITY', w / 2, 188.5, { align: 'center' });

  // Right: Authorized Signatory
  doc.setDrawColor(100, 116, 139);
  doc.setLineWidth(0.45);
  doc.line(w - 86, 170, w - 26, 170);

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10.5);
  doc.setTextColor(15, 23, 42);
  doc.text('Authorized Signatory', w - 56, 176, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(85, 101, 127);
  doc.text(companyName, w - 56, 181.5, { align: 'center' });

  const safeName = cert.studentName.replace(/[^\w]+/g, '_').replace(/^_+|_+$/g, '');
  doc.save(`${cert.certificateId}_${safeName || 'Certificate'}.pdf`);
}
