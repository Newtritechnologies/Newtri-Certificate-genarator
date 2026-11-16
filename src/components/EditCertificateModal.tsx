import React, { useState } from 'react';
import { X } from 'lucide-react';
import { CERT_TYPES, CertType, CombinedCertificate } from '../types';
import { updateCertificateDetails } from '../services/certService';
import { isValidEmail, isValidIsoDate } from '../utils/format';

interface EditCertificateModalProps {
  cert: CombinedCertificate;
  onClose: () => void;
  onSaved: () => void;
}

export const EditCertificateModal: React.FC<EditCertificateModalProps> = ({
  cert,
  onClose,
  onSaved,
}) => {
  const [studentName, setStudentName] = useState(cert.studentName);
  const [college, setCollege] = useState(cert.college);
  const [course, setCourse] = useState(cert.course);
  const [certType, setCertType] = useState<CertType>(cert.certType);
  const [duration, setDuration] = useState(cert.duration);
  const [startDate, setStartDate] = useState(cert.startDate);
  const [endDate, setEndDate] = useState(cert.endDate);
  const [phone, setPhone] = useState(cert.phone);
  const [email, setEmail] = useState(cert.email);
  const [resetSend, setResetSend] = useState(false);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr('');
    if (!studentName.trim() || !college.trim() || !course.trim()) {
      setErr('Student name, college, and course cannot be empty.');
      return;
    }
    if (!isValidIsoDate(startDate) || !isValidIsoDate(endDate) || endDate < startDate) {
      setErr('Start and end dates must be valid and in order.');
      return;
    }
    if (email.trim() && !isValidEmail(email.trim())) {
      setErr('That email address is not valid.');
      return;
    }

    setSaving(true);
    try {
      await updateCertificateDetails(cert, {
        studentName,
        college,
        course,
        certType,
        duration,
        startDate,
        endDate,
        phone,
        email,
        resetSend,
      });
      onSaved();
      onClose();
    } catch (error) {
      setErr(error instanceof Error ? error.message : 'Failed to save changes.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
      <div className="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
        <div className="flex items-start justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 className="text-base font-bold text-slate-900">
              Edit <span className="font-mono">{cert.certificateId}</span>
            </h3>
            <p className="mt-0.5 text-xs text-slate-500">
              The QR code and download link stay identical. Updates reflect immediately.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-1 text-slate-500 hover:bg-slate-100 cursor-pointer"
            aria-label="Close"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="mt-4 space-y-3">
          {err && (
            <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-800">
              {err}
            </div>
          )}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label className="block text-xs font-medium text-slate-600 sm:col-span-2">
              Student Name
              <input
                required
                value={studentName}
                onChange={(e) => setStudentName(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
            <label className="block text-xs font-medium text-slate-600 sm:col-span-2">
              College
              <input
                required
                value={college}
                onChange={(e) => setCollege(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
            <label className="block text-xs font-medium text-slate-600 sm:col-span-2">
              Course / Programme
              <input
                required
                value={course}
                onChange={(e) => setCourse(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
            <label className="block text-xs font-medium text-slate-600">
              Type
              <select
                value={certType}
                onChange={(e) => setCertType(e.target.value as CertType)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              >
                {CERT_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-xs font-medium text-slate-600">
              Duration
              <input
                value={duration}
                onChange={(e) => setDuration(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
            <label className="block text-xs font-medium text-slate-600">
              Start Date
              <input
                type="date"
                required
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
            <label className="block text-xs font-medium text-slate-600">
              End Date
              <input
                type="date"
                required
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
            <label className="block text-xs font-medium text-slate-600">
              Phone (10-digit)
              <input
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
            <label className="block text-xs font-medium text-slate-600">
              Email
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
              />
            </label>
          </div>

          <label className="flex items-center gap-2 pt-1 text-xs text-slate-700">
            <input
              type="checkbox"
              checked={resetSend}
              onChange={(e) => setResetSend(e.target.checked)}
            />
            <span>Mark as &ldquo;not sent yet&rdquo; so it can be sent again</span>
          </label>

          <div className="flex justify-end gap-2 border-t border-slate-200 pt-3">
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saving}
              className="rounded-lg bg-[#1f6feb] px-4 py-2 text-xs font-semibold text-white hover:bg-[#1558c0] disabled:opacity-50 cursor-pointer"
            >
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
