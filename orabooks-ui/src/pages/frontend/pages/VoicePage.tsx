import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import ResourceAttachmentsPanel from '../components/ResourceAttachmentsPanel';
import { CheckCircle2, Mic, Paperclip, RefreshCw, Square } from 'lucide-react';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function VoicePage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [recording, setRecording] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [selectedVoice, setSelectedVoice] = useState<any>(null);
  const [editFields, setEditFields] = useState<Record<string, string>>({});
  const [confirming, setConfirming] = useState(false);

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const chunksRef = useRef<Blob[]>([]);

  const orgId = data?.context?.organization?.id;
  const caps = data?.capabilities || {};
  const threshold = data?.threshold ?? 70;
  const maxMb = Math.round((data?.limits?.max_file_size || 10485760) / 1048576);

  const load = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    const res = await api.voiceDashboard();
    if (res.error) setError(res.error || 'Unable to load voice inputs.');
    else setData((res as any).data);
    setLoading(false);
  };

  useEffect(() => {
    void load();
  }, []);

  const selectVoice = (voice: any) => {
    setSelectedVoice(voice);
    const extracted = voice.extracted_data || {};
    setEditFields({
      transaction_type: extracted.transaction_type || 'expense',
      vendor: extracted.vendor || '',
      customer: extracted.customer || '',
      amount: extracted.amount != null ? String(extracted.amount) : '',
      transaction_date: extracted.transaction_date || '',
      category: extracted.category || '',
      description: extracted.description || '',
    });
  };

  const startRecording = async () => {
    if (!caps.record) return;
    setError('');
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const recorder = new MediaRecorder(stream);
      chunksRef.current = [];
      recorder.ondataavailable = (e) => {
        if (e.data.size > 0) chunksRef.current.push(e.data);
      };
      recorder.onstop = () => {
        stream.getTracks().forEach((t) => t.stop());
        void uploadRecording();
      };
      mediaRecorderRef.current = recorder;
      recorder.start();
      setRecording(true);
    } catch {
      setError('Microphone access denied or unavailable.');
    }
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current && recording) {
      mediaRecorderRef.current.stop();
      setRecording(false);
    }
  };

  const uploadRecording = async () => {
    if (!orgId || chunksRef.current.length === 0) return;

    setUploading(true);
    setError('');
    const blob = new Blob(chunksRef.current, { type: 'audio/webm' });
    const file = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `voice-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const res = await api.uploadVoice(orgId, file, idempotencyKey);
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Voice recorded and transcribed.');
      await load();
      const voice = (res as any).data?.voice_input;
      if (voice) selectVoice(voice);
    }
    setUploading(false);
  };

  const handleConfirm = async () => {
    if (!orgId || !selectedVoice || selectedVoice.status !== 'processed') return;

    setConfirming(true);
    setError('');
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `confirm-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const res = await api.voiceConfirm(orgId, selectedVoice.id, idempotencyKey, {
      ...editFields,
      amount: parseFloat(editFields.amount || '0'),
    });

    if (res.error) {
      setError(res.error);
    } else {
      const voice = (res as any).data?.voice_input;
      setSelectedVoice(voice);
      setSuccess(
        voice?.status === 'escalated'
          ? 'Voice input escalated to AI review.'
          : `Resource created: ${voice?.derived_resource_type || 'resource'} #${voice?.derived_resource_id || ''}`
      );
      await load();
    }
    setConfirming(false);
  };

  const voiceInputs = data?.voice_inputs || [];
  const stats = data?.stats || {};

  return (
    <ClientShell title="Voice Input" eyebrow="SL-052 voice-to-text" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Total Inputs" value={stats.total ?? 0} />
          <Metric label="Processed" value={stats.processed ?? 0} />
          <Metric label="Escalated" value={stats.escalated ?? 0} tone={stats.escalated > 0 ? 'warning' : 'default'} />
          <Metric label="Pending" value={stats.pending ?? 0} />
        </div>

        {caps.record && (
          <div className="glass-panel p-5">
            <div className="flex items-center gap-2 border-b border-border pb-4">
              <Mic className="h-5 w-5 text-primary" />
              <h2 className="font-bold text-ink">Record Voice Command</h2>
            </div>
            <p className="mt-3 text-sm text-slate-600">
              Speak a transaction in any language. Max ~2 minutes, {maxMb}MB upload limit.
            </p>
            <div className="mt-4 flex flex-wrap gap-3">
              {!recording ? (
                <Button onClick={() => void startRecording()} disabled={uploading}>
                  <Mic className="h-4 w-4" />
                  Start Recording
                </Button>
              ) : (
                <Button variant="secondary" onClick={stopRecording}>
                  <Square className="h-4 w-4" />
                  Stop & Transcribe
                </Button>
              )}
              {uploading && <span className="text-sm text-slate-600">Uploading and transcribing...</span>}
            </div>
          </div>
        )}

        {selectedVoice && (
          <div className="glass-panel p-5">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
              <div>
                <h2 className="font-bold text-ink">Transcript & Extracted Data</h2>
                <p className="text-sm text-slate-600">
                  Voice #{selectedVoice.id} · {selectedVoice.status}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {selectedVoice.confidence_avg != null && (
                  <ConfidenceBadge value={selectedVoice.confidence_avg} threshold={threshold} />
                )}
                {selectedVoice.overall_risk_level && <RiskBadge level={selectedVoice.overall_risk_level} />}
              </div>
            </div>

            <label className="mt-4 block text-sm">
              <span className="mb-1 block font-semibold text-slate-700">Transcript</span>
              <textarea
                className={`${fieldClass} min-h-[80px]`}
                readOnly
                value={selectedVoice.edited_transcript || selectedVoice.original_transcript || ''}
              />
            </label>

            {selectedVoice.status === 'processed' && (
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                {(['transaction_type', 'vendor', 'customer', 'amount', 'transaction_date', 'category'] as const).map(
                  (field) => (
                    <label key={field} className="block text-sm">
                      <span className="mb-1 block font-semibold capitalize text-slate-700">{field.replace('_', ' ')}</span>
                      <input
                        className={fieldClass}
                        value={editFields[field] || ''}
                        onChange={(e) => setEditFields((prev) => ({ ...prev, [field]: e.target.value }))}
                      />
                    </label>
                  )
                )}
              </div>
            )}

            {selectedVoice.derived_resource_type && (
              <p className="mt-4 text-sm text-emerald-700">
                Derived {selectedVoice.derived_resource_type} #{selectedVoice.derived_resource_id}
              </p>
            )}

            {orgId && selectedVoice?.id && (
              <div className="mt-4">
                <ResourceAttachmentsPanel
                  orgId={orgId}
                  resourceType="voice_input"
                  resourceId={selectedVoice.id}
                  title="Audio files"
                />
              </div>
            )}

            {caps.confirm && selectedVoice.status === 'processed' && (
              <div className="mt-4 flex flex-wrap gap-2">
                <Link to={`/attachments?resource_type=voice_input&resource_id=${selectedVoice.id}`}>
                  <Button variant="secondary" size="sm">
                    <Paperclip className="h-3.5 w-3.5" />
                    View Audio File
                  </Button>
                </Link>
                <Button onClick={() => void handleConfirm()} disabled={confirming}>
                  <CheckCircle2 className="h-4 w-4" />
                  {confirming ? 'Submitting...' : 'Confirm & Submit'}
                </Button>
              </div>
            )}
          </div>
        )}

        <div className="flex justify-end">
          <Button onClick={load} variant="secondary" size="sm">
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {error && (
          <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
            {success}
          </div>
        )}

        <div className="glass-panel overflow-hidden">
          <div className="border-b border-border px-5 py-4">
            <h2 className="font-bold text-ink">Recent Voice Inputs</h2>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border bg-slate-50/70 text-xs uppercase text-slate-500">
                <th className="px-5 py-3 font-semibold">ID</th>
                <th className="px-5 py-3 font-semibold">Type</th>
                <th className="px-5 py-3 font-semibold">Confidence</th>
                <th className="px-5 py-3 font-semibold">Risk</th>
                <th className="px-5 py-3 font-semibold">Status</th>
                <th className="px-5 py-3 font-semibold">When</th>
                <th className="px-5 py-3 font-semibold">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={7} className="px-5 py-8 text-center text-slate-500">
                    Loading...
                  </td>
                </tr>
              ) : voiceInputs.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-8 text-center text-sm text-slate-500">
                    No voice inputs yet.
                  </td>
                </tr>
              ) : (
                voiceInputs.map((voice: any) => (
                  <tr key={voice.id} className="hover:bg-slate-50/70">
                    <td className="px-5 py-3 font-mono text-xs">#{voice.id}</td>
                    <td className="px-5 py-3 capitalize">{voice.extracted_data?.transaction_type || '—'}</td>
                    <td className="px-5 py-3">
                      {voice.confidence_avg != null ? `${Number(voice.confidence_avg).toFixed(1)}%` : '—'}
                    </td>
                    <td className="px-5 py-3">
                      {voice.overall_risk_level ? <RiskBadge level={voice.overall_risk_level} /> : '—'}
                    </td>
                    <td className="px-5 py-3 capitalize">{voice.status}</td>
                    <td className="px-5 py-3 text-slate-600">{formatDate(voice.created_at)}</td>
                    <td className="px-5 py-3">
                      <Button size="sm" variant="secondary" onClick={() => selectVoice(voice)}>
                        View
                      </Button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </ClientShell>
  );
}

function Metric({
  label,
  value,
  tone = 'default',
}: {
  label: string;
  value: string | number;
  tone?: 'default' | 'warning';
}) {
  const toneClass = tone === 'warning' ? 'border-amber-200 bg-amber-50' : 'border-border bg-white';
  return (
    <div className={`rounded-2xl border p-4 shadow-sm ${toneClass}`}>
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-2 text-2xl font-black text-ink">{value}</p>
    </div>
  );
}

function ConfidenceBadge({ value, threshold }: { value: number; threshold: number }) {
  const low = value < threshold;
  return (
    <span
      className={`badge border font-mono ${low ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
    >
      {Number(value).toFixed(1)}% {low ? 'Low' : 'High'}
    </span>
  );
}

function RiskBadge({ level }: { level: string }) {
  const styles: Record<string, string> = {
    low: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    medium: 'border-amber-200 bg-amber-50 text-amber-800',
    high: 'border-red-200 bg-red-50 text-red-800',
  };
  return (
    <span className={`badge border capitalize ${styles[level] || 'border-slate-200 bg-slate-50 text-slate-700'}`}>
      {level}
    </span>
  );
}

function formatDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
