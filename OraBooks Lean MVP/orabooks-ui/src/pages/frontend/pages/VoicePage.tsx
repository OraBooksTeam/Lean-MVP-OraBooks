import { useEffect, useRef, useState } from 'react';
import WpLink from '../components/WpLink';
import Button from '@/components/Button';
import { api } from '../api';
import ClientShell from '../components/ClientShell';
import ResourceAttachmentsPanel from '../components/ResourceAttachmentsPanel';
import { CheckCircle2, HelpCircle, Mic, Paperclip, RefreshCw, Square, Upload } from 'lucide-react';

const fieldClass =
  'w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm text-ink shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

const EXTRACTED_FIELDS: Array<{
  key: string;
  label: string;
  type?: 'text' | 'number' | 'date' | 'select';
  riskKey?: string;
}> = [
  { key: 'transaction_type', label: 'Type', type: 'select' },
  { key: 'vendor', label: 'Vendor', riskKey: 'vendor_risk' },
  { key: 'customer', label: 'Customer' },
  { key: 'vendor_tax_id', label: 'Vendor Tax ID' },
  { key: 'amount', label: 'Amount', type: 'number', riskKey: 'amount_risk' },
  { key: 'currency', label: 'Currency' },
  { key: 'transaction_date', label: 'Transaction Date', type: 'date' },
  { key: 'due_date', label: 'Due Date', type: 'date' },
  { key: 'subtotal', label: 'Subtotal', type: 'number' },
  { key: 'tax_amount', label: 'Tax Amount', type: 'number' },
  { key: 'tax_rate', label: 'Tax Rate (%)', type: 'number' },
  { key: 'tax_type', label: 'Tax Type' },
  { key: 'tax_jurisdiction', label: 'Tax Jurisdiction' },
  { key: 'tax_registration_number', label: 'Tax Registration #' },
  { key: 'category', label: 'Category', riskKey: 'language_ambiguity_risk' },
  { key: 'description', label: 'Description' },
];

export default function VoicePage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [recording, setRecording] = useState(false);
  const [recordingSeconds, setRecordingSeconds] = useState(0);
  const [uploading, setUploading] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [selectedVoice, setSelectedVoice] = useState<any>(null);
  const [editFields, setEditFields] = useState<Record<string, string>>({});
  const [confirming, setConfirming] = useState(false);
  const [retrying, setRetrying] = useState(false);

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const chunksRef = useRef<Blob[]>([]);
  const timerRef = useRef<number | null>(null);
  const tickRef = useRef<number | null>(null);
  const pollRef = useRef<number | null>(null);

  const orgId = data?.context?.organization?.id;
  const caps = data?.capabilities || {};
  const aiStatus = data?.ai_status || null;
  const threshold = data?.threshold ?? 70;
  const maxMb = Math.round((data?.limits?.max_file_size || 10485760) / 1048576);
  const maxDuration = data?.limits?.max_duration_seconds ?? 120;
  const transactionTypes: string[] = data?.transaction_types || ['expense', 'invoice', 'journal', 'task', 'reminder'];

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
    return () => {
      if (timerRef.current) window.clearTimeout(timerRef.current);
      if (tickRef.current) window.clearInterval(tickRef.current);
      if (pollRef.current) window.clearInterval(pollRef.current);
    };
  }, []);

  const selectVoice = (voice: any) => {
    setSelectedVoice(voice);
    const extracted = voice.extracted_data || {};
    const next: Record<string, string> = {};
    for (const field of EXTRACTED_FIELDS) {
      const val = extracted[field.key];
      next[field.key] = val != null ? String(val) : '';
    }
    setEditFields(next);
  };

  const clearRecordingTimers = () => {
    if (timerRef.current) {
      window.clearTimeout(timerRef.current);
      timerRef.current = null;
    }
    if (tickRef.current) {
      window.clearInterval(tickRef.current);
      tickRef.current = null;
    }
    setRecordingSeconds(0);
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
        clearRecordingTimers();
        void uploadRecording();
      };
      mediaRecorderRef.current = recorder;
      recorder.start();
      setRecording(true);
      tickRef.current = window.setInterval(() => {
        setRecordingSeconds((s) => s + 1);
      }, 1000);
      timerRef.current = window.setTimeout(() => {
        stopRecording();
      }, maxDuration * 1000);
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

  const pollVoiceStatus = (voiceId: number) => {
    if (pollRef.current) window.clearInterval(pollRef.current);
    pollRef.current = window.setInterval(async () => {
      if (!orgId) return;
      const res = await api.voiceGet(orgId, voiceId);
      if (res.error) return;
      const voice = (res as any).data?.voice_input;
      if (!voice || voice.status === 'pending') return;
      window.clearInterval(pollRef.current!);
      pollRef.current = null;
      selectVoice(voice);
      await load();
    }, 2000);
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
      setSuccess('Voice recorded. Transcription in progress…');
      await load();
      const voice = (res as any).data?.voice_input;
      if (voice) {
        selectVoice(voice);
        if (voice.status === 'pending') pollVoiceStatus(voice.id);
        else setSuccess('Voice transcribed. Review extracted data below.');
      }
    }
    setUploading(false);
  };

  const uploadSelectedFile = async () => {
    if (!orgId || !selectedFile) {
      setError('Select an audio file first.');
      return;
    }

    setUploading(true);
    setError('');
    const idempotencyKey =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `voice-file-${Date.now()}-${Math.random().toString(36).slice(2)}`;

    const res = await api.uploadVoice(orgId, selectedFile, idempotencyKey);
    if (res.error) {
      setError(res.error);
    } else {
      setSuccess('Audio uploaded. Transcription in progress…');
      await load();
      const voice = (res as any).data?.voice_input;
      if (voice) {
        selectVoice(voice);
        if (voice.status === 'pending') pollVoiceStatus(voice.id);
        else setSuccess('Voice transcribed. Review extracted data below.');
      }
      setSelectedFile(null);
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

    const payload: Record<string, unknown> = { ...editFields };
    if (payload.amount != null && payload.amount !== '') {
      payload.amount = parseFloat(String(payload.amount));
    }
    if (payload.subtotal != null && payload.subtotal !== '') {
      payload.subtotal = parseFloat(String(payload.subtotal));
    }
    if (payload.tax_amount != null && payload.tax_amount !== '') {
      payload.tax_amount = parseFloat(String(payload.tax_amount));
    }
    if (payload.tax_rate != null && payload.tax_rate !== '') {
      payload.tax_rate = parseFloat(String(payload.tax_rate));
    }

    const res = await api.voiceConfirm(orgId, selectedVoice.id, idempotencyKey, payload);

    if (res.error) {
      const msg = res.error || 'Confirm failed';
      setError(msg.includes('Duplicate') || msg.includes('already been submitted') ? 'Duplicate submission detected.' : msg);
    } else {
      const voice = (res as any).data?.voice_input;
      setSelectedVoice(voice);
      setSuccess(
        voice?.status === 'escalated'
          ? 'Sent to AI review queue (low confidence or elevated risk).'
          : `Resource draft created: ${voice?.derived_resource_type || 'resource'} #${voice?.derived_resource_id || ''}`
      );
      await load();
    }
    setConfirming(false);
  };

  const handleRetry = async () => {
    if (!orgId || !selectedVoice?.id || retrying) return;

    setRetrying(true);
    setError('');
    const res = await api.voiceRetry(orgId, selectedVoice.id);
    if (res.error) {
      setError(res.error || 'Retry failed.');
    } else {
      setSuccess('Retry requested. Transcription is processing again.');
      const voice = (res as any).data?.voice_input;
      if (voice) {
        selectVoice(voice);
        if (voice.status === 'pending') pollVoiceStatus(voice.id);
      }
      await load();
    }
    setRetrying(false);
  };

  const voiceInputs = data?.voice_inputs || [];
  const stats = data?.stats || {};
  const fieldConfidences = selectedVoice?.field_confidences || selectedVoice?.extracted_data?.field_confidences || {};
  const riskScores = selectedVoice?.risk_scores || {};

  return (
    <ClientShell title="Voice Input" eyebrow="SL-052 voice-to-text" organization={data?.context?.organization}>
      <div className="space-y-5">
        <div
          className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-slate-700"
          title="Speak transaction in any language. Max 2 minutes."
        >
          Speak a transaction in any language. Max {maxDuration / 60} minutes, {maxMb}MB upload limit.
          Audio is encrypted and retained for {data?.limits?.retention_days ?? 90} days unless legal hold applies.
        </div>

        {aiStatus && (
          <div
            className={`rounded-xl border p-4 text-sm ${aiStatus.speech_provider !== 'mvp-stub' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'}`}
          >
            <p className="font-semibold">
              Speech: {aiStatus.speech_provider || 'mvp-stub'} ({aiStatus.speech_model_version || 'mvp-stub-1.0'})
            </p>
            {aiStatus.speech_webhook_health?.status && aiStatus.speech_provider === 'speech-webhook' && (
              <p className="mt-1 text-xs">
                Webhook health: {aiStatus.speech_webhook_health.status}
                {aiStatus.speech_webhook_health.version ? ` (${aiStatus.speech_webhook_health.version})` : ''}
              </p>
            )}
            {aiStatus.speech_provider === 'mvp-stub' && (
              <p className="mt-1">
                Real speech transcription is not configured. Configure OpenAI, Azure OpenAI, or Speech Webhook for real voice-to-text.
              </p>
            )}
          </div>
        )}

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <Metric label="Total Inputs" value={stats.total ?? 0} />
          <Metric label="Processed" value={stats.processed ?? 0} />
          <Metric label="Pending" value={stats.pending ?? 0} />
          <Metric label="Escalated" value={stats.escalated ?? 0} tone={stats.escalated > 0 ? 'warning' : 'default'} />
          <Metric label="Failed" value={stats.failed ?? 0} tone={stats.failed > 0 ? 'warning' : 'default'} />
        </div>

        {caps.record ? (
          <div className="glass-panel p-5">
            <div className="flex items-center gap-2 border-b border-border pb-4">
              <Mic className="h-5 w-5 text-primary" />
              <h2 className="font-bold text-ink">Record Voice Command</h2>
            </div>
            <p className="mt-3 text-sm text-slate-600" title="Speak transaction in any language. Max 2 minutes.">
              Use the microphone to describe an expense, invoice, or journal entry.
            </p>
            <div className="mt-4 flex flex-wrap items-center gap-3">
              {!recording ? (
                <Button onClick={() => void startRecording()} disabled={uploading} title="Speak transaction. Any language supported.">
                  <Mic className="h-4 w-4" />
                  Start Recording
                </Button>
              ) : (
                <Button variant="secondary" onClick={stopRecording} title="Stop recording and transcribe.">
                  <Square className="h-4 w-4" />
                  Stop & Transcribe ({formatRecordingTime(recordingSeconds)} / {formatRecordingTime(maxDuration)})
                </Button>
              )}
              {uploading && <span className="text-sm text-slate-600">Uploading and transcribing…</span>}
            </div>

            <div className="mt-4 rounded-lg border border-border bg-slate-50 p-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">No microphone? Upload audio instead</p>
              <p className="mt-1 text-sm text-slate-600">Supported: WEBM, MP3, WAV, OGG, M4A (max {maxMb}MB).</p>
              <div className="mt-3 flex flex-wrap items-center gap-3">
                <label
                  htmlFor="voice-audio-file"
                  className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
                    selectedFile
                      ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                      : 'border-primary/40 bg-primary/10 text-primary hover:bg-primary/20'
                  }`}
                  title="Choose an audio file for transcription"
                >
                  <Upload className="h-4 w-4" />
                  {selectedFile ? 'Change Audio File' : 'Choose Audio File'}
                </label>
                <input
                  id="voice-audio-file"
                  type="file"
                  accept="audio/*,.webm,.mp3,.wav,.ogg,.m4a"
                  onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                  className="sr-only"
                  title="Upload an audio file for transcription"
                />
                <Button
                  variant="secondary"
                  className="cursor-pointer border-success transition bg-accent text-white shadow-sm hover:border-success hover:bg-success/90"
                  onClick={() => void uploadSelectedFile()}
                  disabled={uploading}
                  title="Upload audio and transcribe"
                >
                  Upload Audio
                </Button>
                {selectedFile && <span className="text-xs font-medium text-slate-700">{selectedFile.name}</span>}
                {!selectedFile && !uploading && (
                  <span className="text-xs font-medium text-primary">Step 1: Choose Audio File, then click Upload Audio.</span>
                )}
              </div>
            </div>
          </div>
        ) : (
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            You have view-only access. Recording and confirm require Owner, Admin, or Staff role.
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
                {(selectedVoice.ai_provider || selectedVoice.ai_model_version) && (
                  <p className="mt-1 text-xs text-slate-500">
                    Provider: {selectedVoice.ai_provider || 'unknown'} · Model: {selectedVoice.ai_model_version || 'unknown'}
                  </p>
                )}
              </div>
              <div className="flex flex-wrap gap-2">
                {selectedVoice.confidence_avg != null && (
                  <ConfidenceBadge value={selectedVoice.confidence_avg} threshold={threshold} />
                )}
                {selectedVoice.overall_risk_level && (
                  <OverallRiskBadge level={selectedVoice.overall_risk_level} />
                )}
              </div>
            </div>

            <label className="mt-4 block text-sm">
              <span className="mb-1 flex items-center gap-1 font-semibold text-slate-700">
                Transcript
                <HelpCircle className="h-3.5 w-3.5 text-slate-400" title="What you said (readonly transcript)." />
              </span>
              <textarea
                className={`${fieldClass} min-h-[80px] bg-slate-50`}
                readOnly
                title="What you said."
                value={selectedVoice.edited_transcript || selectedVoice.original_transcript || ''}
              />
            </label>

            {selectedVoice.status === 'processed' && (
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                {EXTRACTED_FIELDS.map((field) => (
                  <label key={field.key} className="block text-sm">
                    <span className="mb-1 flex flex-wrap items-center gap-2 font-semibold text-slate-700">
                      {field.label}
                      {fieldConfidences[field.key] != null && (
                        <ConfidenceBadge value={Number(fieldConfidences[field.key])} threshold={threshold} compact />
                      )}
                      {field.riskKey && riskScores[field.riskKey] != null && (
                        <FieldRiskBadge score={Number(riskScores[field.riskKey])} field={field.label} />
                      )}
                    </span>
                    {field.type === 'select' ? (
                      <select
                        className={fieldClass}
                        value={editFields[field.key] || 'expense'}
                        onChange={(e) => setEditFields((prev) => ({ ...prev, [field.key]: e.target.value }))}
                        title="Edit manually if incorrect."
                      >
                        {transactionTypes.map((t) => (
                          <option key={t} value={t}>
                            {t.replace('_', ' ')}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <input
                        type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                        className={fieldClass}
                        value={editFields[field.key] || ''}
                        onChange={(e) => setEditFields((prev) => ({ ...prev, [field.key]: e.target.value }))}
                        title="Edit manually if incorrect."
                      />
                    )}
                  </label>
                ))}
              </div>
            )}

            {(selectedVoice.status === 'failed' || selectedVoice.status === 'dead_letter') && selectedVoice.dead_letter_reason && (
              <p className="mt-4 text-sm text-red-700">
                Transcription failed: {selectedVoice.dead_letter_reason}
              </p>
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

            {caps.confirm && selectedVoice.status === 'processed' && !selectedVoice.derived_resource_id && (
              <div className="mt-4 flex flex-wrap gap-2">
                <WpLink to={`/attachments?resource_type=voice_input&resource_id=${selectedVoice.id}`}>
                  <Button variant="secondary" size="sm">
                    <Paperclip className="h-3.5 w-3.5" />
                    View Audio File
                  </Button>
                </WpLink>
                <Button
                  onClick={() => void handleConfirm()}
                  disabled={confirming}
                  className="bg-success hover:bg-success/90"
                  title="Create resource and send for approval (or AI review if low confidence/high risk)."
                >
                  <CheckCircle2 className="h-4 w-4" />
                  {confirming ? 'Submitting…' : 'Confirm & Submit'}
                </Button>
              </div>
            )}

            {caps.retry && (selectedVoice.status === 'failed' || selectedVoice.status === 'dead_letter') && (
              <div className="mt-4 flex flex-wrap gap-2">
                <Button variant="secondary" onClick={() => void handleRetry()} disabled={retrying}>
                  <RefreshCw className="h-4 w-4" />
                  {retrying ? 'Retrying…' : 'Retry Transcription'}
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
                    Loading…
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
                      {voice.overall_risk_level ? <OverallRiskBadge level={voice.overall_risk_level} compact /> : '—'}
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

function ConfidenceBadge({
  value,
  threshold,
  compact = false,
}: {
  value: number;
  threshold: number;
  compact?: boolean;
}) {
  const level = value >= 85 ? 'High' : value >= threshold ? 'Medium' : 'Low';
  const cls =
    level === 'High'
      ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
      : level === 'Medium'
        ? 'border-amber-200 bg-amber-50 text-amber-800'
        : 'border-orange-200 bg-orange-50 text-orange-800';

  return (
    <span
      className={`badge border font-semibold ${compact ? 'text-[10px]' : ''} ${cls}`}
      title="AI confidence. Verify if low."
    >
      {value.toFixed(compact ? 0 : 1)}% {level}
    </span>
  );
}

function OverallRiskBadge({ level, compact = false }: { level: string; compact?: boolean }) {
  const styles: Record<string, string> = {
    low: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    medium: 'border-amber-200 bg-amber-50 text-amber-800',
    high: 'border-red-200 bg-red-50 text-red-800',
  };
  return (
    <span
      className={`badge border capitalize ${compact ? 'text-[10px]' : ''} ${styles[level] || 'border-slate-200 bg-slate-50 text-slate-700'}`}
      title="Aggregated risk level used for routing."
    >
      {compact ? level : `Overall Risk: ${level}`}
    </span>
  );
}

function FieldRiskBadge({ score, field }: { score: number; field: string }) {
  const level = score >= 70 ? 'high' : score >= 30 ? 'medium' : 'low';
  const styles: Record<string, string> = {
    low: 'text-emerald-700',
    medium: 'text-amber-700',
    high: 'text-red-700',
  };
  return (
    <span className={`text-[10px] font-semibold uppercase ${styles[level]}`} title={`Risk indicator for ${field}.`}>
      {field} risk: {level}
    </span>
  );
}

function formatRecordingTime(seconds: number) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

function formatDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
}
