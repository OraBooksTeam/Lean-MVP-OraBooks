import { useEffect, useState } from 'react';
import Button from '@/components/Button';
import AdminPageShell from '@/components/AdminPageShell';
import { api } from '../api';
import { AudioLines, CheckCircle2, Link2, RefreshCw, ShieldCheck, XCircle } from 'lucide-react';

interface PlatformSettings {
  block_same_email_domain: boolean;
  partner_commission_for_staff_viewer: boolean;
  audit_retention_days: number;
  jwt_expiry: number;
  refresh_token_expiry: number;
  openai_api_key: string;
  openai_chat_model: string;
  openai_whisper_model: string;
  azure_openai_endpoint: string;
  azure_openai_key: string;
  azure_openai_deployment: string;
  azure_openai_whisper_deployment: string;
  azure_openai_api_version: string;
  azure_document_intelligence_endpoint: string;
  azure_document_intelligence_key: string;
  azure_document_intelligence_model: string;
  azure_document_intelligence_api_version: string;
  speech_webhook_url: string;
  speech_webhook_token: string;
  speech_webhook_model: string;
  speech_webhook_health_url: string;
}

const defaults: PlatformSettings = {
  block_same_email_domain: false,
  partner_commission_for_staff_viewer: false,
  audit_retention_days: 365,
  jwt_expiry: 3600,
  refresh_token_expiry: 604800,
  openai_api_key: '',
  openai_chat_model: 'gpt-4o-mini',
  openai_whisper_model: 'whisper-1',
  azure_openai_endpoint: '',
  azure_openai_key: '',
  azure_openai_deployment: 'gpt-4o-mini',
  azure_openai_whisper_deployment: '',
  azure_openai_api_version: '2024-06-01',
  azure_document_intelligence_endpoint: '',
  azure_document_intelligence_key: '',
  azure_document_intelligence_model: 'prebuilt-receipt',
  azure_document_intelligence_api_version: '2023-07-31',
  speech_webhook_url: '',
  speech_webhook_token: '',
  speech_webhook_model: 'webhook-v1',
  speech_webhook_health_url: '',
};

type DeployCheck = {
  id: string;
  label: string;
  ok: boolean;
  detail?: string;
};

type DeployChecksResult = {
  ok: boolean;
  checks: DeployCheck[];
  timestamp?: string;
  environment?: Record<string, unknown>;
};

export default function AdminSettings() {
  const [settings, setSettings] = useState<PlatformSettings>(defaults);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [deployChecks, setDeployChecks] = useState<DeployChecksResult | null>(null);
  const [deployLoading, setDeployLoading] = useState(false);
  const [deployError, setDeployError] = useState('');
  const [deployRepairing, setDeployRepairing] = useState(false);
  const [deployRepairMessage, setDeployRepairMessage] = useState('');
  const [speechCheckLoading, setSpeechCheckLoading] = useState(false);
  const [speechCheckError, setSpeechCheckError] = useState('');
  const [speechCheckResult, setSpeechCheckResult] = useState<{
    speech_provider: string;
    speech_model_version: string;
    speech_webhook_configured: boolean;
    speech_webhook_health: { status?: string; message?: string; version?: string };
    checked_at?: string;
  } | null>(null);

  useEffect(() => {
    setLoading(true);
    setError('');
    api.platformSettingsGet().then((res) => {
      if (res.error) {
        setError(res.error);
      } else if (res.data) {
        setSettings({
          block_same_email_domain: Boolean(res.data.block_same_email_domain),
          partner_commission_for_staff_viewer: Boolean(res.data.partner_commission_for_staff_viewer),
          audit_retention_days: Number(res.data.audit_retention_days) || defaults.audit_retention_days,
          jwt_expiry: Number(res.data.jwt_expiry) || defaults.jwt_expiry,
          refresh_token_expiry: Number(res.data.refresh_token_expiry) || defaults.refresh_token_expiry,
          openai_api_key: String(res.data.openai_api_key || defaults.openai_api_key),
          openai_chat_model: String(res.data.openai_chat_model || defaults.openai_chat_model),
          openai_whisper_model: String(res.data.openai_whisper_model || defaults.openai_whisper_model),
          azure_openai_endpoint: String(res.data.azure_openai_endpoint || defaults.azure_openai_endpoint),
          azure_openai_key: String(res.data.azure_openai_key || defaults.azure_openai_key),
          azure_openai_deployment: String(res.data.azure_openai_deployment || defaults.azure_openai_deployment),
          azure_openai_whisper_deployment: String(
            res.data.azure_openai_whisper_deployment || defaults.azure_openai_whisper_deployment
          ),
          azure_openai_api_version: String(res.data.azure_openai_api_version || defaults.azure_openai_api_version),
          azure_document_intelligence_endpoint: String(
            res.data.azure_document_intelligence_endpoint || defaults.azure_document_intelligence_endpoint
          ),
          azure_document_intelligence_key: String(
            res.data.azure_document_intelligence_key || defaults.azure_document_intelligence_key
          ),
          azure_document_intelligence_model: String(
            res.data.azure_document_intelligence_model || defaults.azure_document_intelligence_model
          ),
          azure_document_intelligence_api_version: String(
            res.data.azure_document_intelligence_api_version || defaults.azure_document_intelligence_api_version
          ),
          speech_webhook_url: String(res.data.speech_webhook_url || defaults.speech_webhook_url),
          speech_webhook_token: String(res.data.speech_webhook_token || defaults.speech_webhook_token),
          speech_webhook_model: String(res.data.speech_webhook_model || defaults.speech_webhook_model),
          speech_webhook_health_url: String(
            res.data.speech_webhook_health_url || defaults.speech_webhook_health_url
          ),
        });
      }
      setLoading(false);
    });
  }, []);

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setMessage('');
    api.platformSettingsSave(settings).then((res) => {
      if (res.error) {
        setError(res.error);
      } else {
        setMessage('Settings saved.');
        if (res.data) {
          setSettings({
            block_same_email_domain: Boolean(res.data.block_same_email_domain),
            partner_commission_for_staff_viewer: Boolean(res.data.partner_commission_for_staff_viewer),
            audit_retention_days: Number(res.data.audit_retention_days),
            jwt_expiry: Number(res.data.jwt_expiry),
            refresh_token_expiry: Number(res.data.refresh_token_expiry),
            openai_api_key: String(res.data.openai_api_key || ''),
            openai_chat_model: String(res.data.openai_chat_model || defaults.openai_chat_model),
            openai_whisper_model: String(res.data.openai_whisper_model || defaults.openai_whisper_model),
            azure_openai_endpoint: String(res.data.azure_openai_endpoint || ''),
            azure_openai_key: String(res.data.azure_openai_key || ''),
            azure_openai_deployment: String(res.data.azure_openai_deployment || defaults.azure_openai_deployment),
            azure_openai_whisper_deployment: String(res.data.azure_openai_whisper_deployment || ''),
            azure_openai_api_version: String(res.data.azure_openai_api_version || defaults.azure_openai_api_version),
            azure_document_intelligence_endpoint: String(res.data.azure_document_intelligence_endpoint || ''),
            azure_document_intelligence_key: String(res.data.azure_document_intelligence_key || ''),
            azure_document_intelligence_model: String(
              res.data.azure_document_intelligence_model || defaults.azure_document_intelligence_model
            ),
            azure_document_intelligence_api_version: String(
              res.data.azure_document_intelligence_api_version || defaults.azure_document_intelligence_api_version
            ),
            speech_webhook_url: String(res.data.speech_webhook_url || ''),
            speech_webhook_token: String(res.data.speech_webhook_token || ''),
            speech_webhook_model: String(res.data.speech_webhook_model || 'webhook-v1'),
            speech_webhook_health_url: String(res.data.speech_webhook_health_url || ''),
          });
        }
      }
      setSaving(false);
    });
  };

  const runDeployChecks = () => {
    setDeployLoading(true);
    setDeployError('');
    api.deployChecks().then((res) => {
      if (res.error) {
        setDeployError(typeof res.error === 'string' ? res.error : 'Deploy checks failed.');
        setDeployChecks(null);
      } else {
        setDeployChecks((res as { data?: DeployChecksResult }).data || null);
      }
      setDeployLoading(false);
    });
  };

  const repairDeployIssues = () => {
    setDeployRepairing(true);
    setDeployError('');
    setDeployRepairMessage('');
    api.deployRepair().then((res) => {
      if (res.error) {
        setDeployError(typeof res.error === 'string' ? res.error : 'Repair failed.');
      } else {
        const payload = (res as { data?: { repaired?: string[]; checks?: DeployChecksResult } }).data;
        setDeployChecks(payload?.checks || null);
        const repaired = payload?.repaired || [];
        setDeployRepairMessage(
          repaired.length > 0
            ? `Scheduled missing crons: ${repaired.join(', ')}`
            : 'No cron repairs were needed.'
        );
      }
      setDeployRepairing(false);
    });
  };

  const runSpeechWebhookCheck = () => {
    setSpeechCheckLoading(true);
    setSpeechCheckError('');
    api.speechWebhookCheck().then((res) => {
      if (res.error) {
        setSpeechCheckError(typeof res.error === 'string' ? res.error : 'Speech webhook check failed.');
        setSpeechCheckResult(null);
      } else {
        setSpeechCheckResult((res as { data?: any }).data || null);
      }
      setSpeechCheckLoading(false);
    });
  };

  const hasCronFailures = Boolean(
    deployChecks?.checks?.some((check) => check.id.startsWith('cron_') && !check.ok)
  );
  const speechWebhookConfigured = settings.speech_webhook_url.trim().length > 0;
  const speechHealthConfigured = settings.speech_webhook_health_url.trim().length > 0;

  return (
    <AdminPageShell
      title="Platform Settings"
      description="Security, authentication, audit retention, and post-deploy verification."
    >
      {error && <p className="text-sm text-danger">{error}</p>}
      {message && <p className="text-sm text-success">{message}</p>}

      {loading ? (
        <div className="h-64 animate-pulse rounded-2xl border border-border bg-white" />
      ) : (
        <form onSubmit={handleSubmit} className="glass-panel overflow-hidden">
          <div className="divide-y divide-border">
            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-start sm:justify-between">
              <span className="text-sm font-semibold text-ink">Block same email domain</span>
              <span className="flex items-start gap-3 text-sm text-ink-secondary sm:max-w-xl">
                <input
                  type="checkbox"
                  className="mt-1 h-4 w-4 rounded border-border text-primary"
                  checked={settings.block_same_email_domain}
                  onChange={(e) =>
                    setSettings((prev) => ({ ...prev, block_same_email_domain: e.target.checked }))
                  }
                />
                Block partner attribution when customer email domain matches partner email domain
              </span>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-start sm:justify-between">
              <span className="text-sm font-semibold text-ink">Staff/viewer commission access</span>
              <span className="flex items-start gap-3 text-sm text-ink-secondary sm:max-w-xl">
                <input
                  type="checkbox"
                  className="mt-1 h-4 w-4 rounded border-border text-primary"
                  checked={settings.partner_commission_for_staff_viewer}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      partner_commission_for_staff_viewer: e.target.checked,
                    }))
                  }
                />
                Allow Staff and Viewer roles to access partner commission dashboard
              </span>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
              <span className="text-sm font-semibold text-ink">Audit log retention (days)</span>
              <div className="sm:max-w-xs">
                <input
                  type="number"
                  min={30}
                  max={3650}
                  className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                  value={settings.audit_retention_days}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      audit_retention_days: Number(e.target.value),
                    }))
                  }
                />
                <p className="mt-1 text-xs text-slate-500">Days to retain audit logs before archival</p>
              </div>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
              <span className="text-sm font-semibold text-ink">JWT expiry (seconds)</span>
              <div className="sm:max-w-xs">
                <input
                  type="number"
                  min={60}
                  max={86400}
                  className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                  value={settings.jwt_expiry}
                  onChange={(e) =>
                    setSettings((prev) => ({ ...prev, jwt_expiry: Number(e.target.value) }))
                  }
                />
                <p className="mt-1 text-xs text-slate-500">Default: 900 (15 minutes)</p>
              </div>
            </label>

            <label className="flex flex-col gap-2 p-6 sm:flex-row sm:items-center sm:justify-between">
              <span className="text-sm font-semibold text-ink">Refresh token expiry (seconds)</span>
              <div className="sm:max-w-xs">
                <input
                  type="number"
                  min={3600}
                  max={2592000}
                  className="w-full rounded-lg border border-border px-3 py-2 text-sm"
                  value={settings.refresh_token_expiry}
                  onChange={(e) =>
                    setSettings((prev) => ({
                      ...prev,
                      refresh_token_expiry: Number(e.target.value),
                    }))
                  }
                />
                <p className="mt-1 text-xs text-slate-500">Default: 604800 (7 days)</p>
              </div>
            </label>

            <div className="p-6">
              <div className="mb-6 overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-blue-50 to-white">
                <div className="border-b border-violet-100 bg-white/70 px-5 py-4">
                  <h3 className="text-sm font-bold text-ink">Central AI / OCR Keys</h3>
                  <p className="text-xs text-slate-600">
                    Set keys once here. OCR, Classification, and Voice modules will use this shared configuration.
                  </p>
                </div>

                <div className="grid gap-4 px-5 py-5 lg:grid-cols-2">
                  <label className="space-y-1 lg:col-span-2">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">OpenAI API key</span>
                    <input
                      type="password"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.openai_api_key}
                      onChange={(e) => setSettings((prev) => ({ ...prev, openai_api_key: e.target.value }))}
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">OpenAI chat model</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.openai_chat_model}
                      onChange={(e) => setSettings((prev) => ({ ...prev, openai_chat_model: e.target.value }))}
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">OpenAI whisper model</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.openai_whisper_model}
                      onChange={(e) => setSettings((prev) => ({ ...prev, openai_whisper_model: e.target.value }))}
                    />
                  </label>

                  <label className="space-y-1 lg:col-span-2">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Azure OpenAI endpoint</span>
                    <input
                      type="url"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_openai_endpoint}
                      onChange={(e) => setSettings((prev) => ({ ...prev, azure_openai_endpoint: e.target.value }))}
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Azure OpenAI key</span>
                    <input
                      type="password"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_openai_key}
                      onChange={(e) => setSettings((prev) => ({ ...prev, azure_openai_key: e.target.value }))}
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Azure OpenAI deployment</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_openai_deployment}
                      onChange={(e) => setSettings((prev) => ({ ...prev, azure_openai_deployment: e.target.value }))}
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Azure whisper deployment</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_openai_whisper_deployment}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, azure_openai_whisper_deployment: e.target.value }))
                      }
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Azure API version</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_openai_api_version}
                      onChange={(e) => setSettings((prev) => ({ ...prev, azure_openai_api_version: e.target.value }))}
                    />
                  </label>

                  <label className="space-y-1 lg:col-span-2">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Azure Document Intelligence endpoint</span>
                    <input
                      type="url"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_document_intelligence_endpoint}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, azure_document_intelligence_endpoint: e.target.value }))
                      }
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Document Intelligence key</span>
                    <input
                      type="password"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_document_intelligence_key}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, azure_document_intelligence_key: e.target.value }))
                      }
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Document model</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_document_intelligence_model}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, azure_document_intelligence_model: e.target.value }))
                      }
                    />
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Document API version</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.azure_document_intelligence_api_version}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, azure_document_intelligence_api_version: e.target.value }))
                      }
                    />
                  </label>
                </div>
              </div>

              <div className="overflow-hidden rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 via-cyan-50 to-white">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-sky-100 bg-white/70 px-5 py-4">
                  <div className="flex items-center gap-2">
                    <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                      <AudioLines className="h-4 w-4" />
                    </span>
                    <div>
                      <h3 className="text-sm font-bold text-ink">Speech Webhook Configuration</h3>
                      <p className="text-xs text-slate-600">
                        Configure external speech-to-text provider connection for Voice diagnostics and transcription.
                      </p>
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <Button
                      type="button"
                      variant="secondary"
                      size="sm"
                      onClick={runSpeechWebhookCheck}
                      disabled={speechCheckLoading || saving}
                    >
                      {speechCheckLoading ? 'Checking…' : 'Test speech webhook'}
                    </Button>
                    <span
                      className={`rounded-full border px-2 py-1 ${
                        speechWebhookConfigured
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                          : 'border-amber-200 bg-amber-50 text-amber-700'
                      }`}
                    >
                      {speechWebhookConfigured ? 'Webhook configured' : 'Webhook missing'}
                    </span>
                    <span
                      className={`rounded-full border px-2 py-1 ${
                        speechHealthConfigured
                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                          : 'border-slate-200 bg-slate-100 text-slate-600'
                      }`}
                    >
                      {speechHealthConfigured ? 'Health URL set' : 'Health URL optional'}
                    </span>
                  </div>
                </div>

                <div className="grid gap-4 px-5 py-5 lg:grid-cols-2">
                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Speech webhook URL</span>
                    <input
                      type="url"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      placeholder="https://speech.example.com/transcribe"
                      value={settings.speech_webhook_url}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, speech_webhook_url: e.target.value }))
                      }
                    />
                    <p className="text-xs text-slate-500">Main transcription endpoint.</p>
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Speech webhook token</span>
                    <input
                      type="password"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.speech_webhook_token}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, speech_webhook_token: e.target.value }))
                      }
                    />
                    <p className="text-xs text-slate-500">Bearer token for authenticated speech requests.</p>
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Speech webhook model</span>
                    <input
                      type="text"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      value={settings.speech_webhook_model}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, speech_webhook_model: e.target.value }))
                      }
                    />
                    <p className="text-xs text-slate-500">Displayed in Voice diagnostics and transcript metadata.</p>
                  </label>

                  <label className="space-y-1">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">Speech webhook health URL</span>
                    <input
                      type="url"
                      className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                      placeholder="https://speech.example.com/health"
                      value={settings.speech_webhook_health_url}
                      onChange={(e) =>
                        setSettings((prev) => ({ ...prev, speech_webhook_health_url: e.target.value }))
                      }
                    />
                    <p className="text-xs text-slate-500">Optional endpoint for provider health checks.</p>
                  </label>
                </div>

                <div className="mx-5 mb-5 rounded-xl border border-sky-100 bg-white/80 px-4 py-3 text-xs text-slate-600">
                  <p className="mb-2 flex items-center gap-1 font-semibold text-slate-700">
                    <Link2 className="h-3.5 w-3.5" />
                    Live verification checklist
                  </p>
                  <p>1. Save settings here.</p>
                  <p>2. Open Voice page.</p>
                  <p>3. Check Speech Diagnostics card for provider, model, and health.</p>
                </div>

                {speechCheckError && (
                  <div className="mx-5 mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">
                    {speechCheckError}
                  </div>
                )}

                {speechCheckResult && (
                  <div className="mx-5 mb-5 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-700">
                    <p className="font-semibold text-slate-800">Last speech webhook check</p>
                    <p>Provider: {speechCheckResult.speech_provider || 'mvp-stub'}</p>
                    <p>Model: {speechCheckResult.speech_model_version || 'mvp-stub-1.0'}</p>
                    <p>
                      Health: {speechCheckResult.speech_webhook_health?.status || 'unknown'}
                      {speechCheckResult.speech_webhook_health?.version
                        ? ` (${speechCheckResult.speech_webhook_health.version})`
                        : ''}
                    </p>
                    {speechCheckResult.speech_webhook_health?.message && (
                      <p>Error: {speechCheckResult.speech_webhook_health.message}</p>
                    )}
                    {speechCheckResult.checked_at && <p>Checked at: {speechCheckResult.checked_at}</p>}
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="border-t border-border bg-slate-50/60 px-6 py-4">
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving…' : 'Save settings'}
            </Button>
          </div>
        </form>
      )}

      <section className="glass-panel mt-6 overflow-hidden" id="deploy-checks">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/60 px-6 py-4">
          <div className="flex items-center gap-2">
            <ShieldCheck className="h-5 w-5 text-primary" />
            <div>
              <h2 className="text-sm font-bold text-ink">Post-deploy verification</h2>
              <p className="text-xs text-slate-500">
                Confirms shared table prefix, SL-021 schema, crons, and async handlers after upload.
              </p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button onClick={runDeployChecks} variant="secondary" size="sm" disabled={deployLoading || deployRepairing}>
              <RefreshCw className={`h-4 w-4 ${deployLoading ? 'animate-spin' : ''}`} />
              {deployLoading ? 'Running…' : 'Run checks'}
            </Button>
            {hasCronFailures && (
              <Button onClick={repairDeployIssues} size="sm" disabled={deployLoading || deployRepairing}>
                {deployRepairing ? 'Repairing…' : 'Repair crons'}
              </Button>
            )}
          </div>
        </div>

        {deployRepairMessage && (
          <p className="px-6 pt-4 text-sm text-success">{deployRepairMessage}</p>
        )}

        {deployError && (
          <p className="px-6 py-4 text-sm text-danger">{deployError}</p>
        )}

        {deployChecks && (
          <div className="px-6 py-4">
            <div
              className={`mb-4 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold ${
                deployChecks.ok
                  ? 'border-success/30 bg-success/10 text-success'
                  : 'border-danger/30 bg-danger/10 text-danger'
              }`}
            >
              {deployChecks.ok ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
              {deployChecks.ok ? 'All deploy checks passed' : 'One or more deploy checks failed'}
            </div>

            {deployChecks.environment && (
              <div className="mb-4 grid gap-2 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-3">
                {Object.entries(deployChecks.environment).map(([key, value]) => (
                  <p key={key}>
                    <span className="font-semibold text-ink">{key}:</span>{' '}
                    {value === null || value === undefined || value === '' ? '—' : String(value)}
                  </p>
                ))}
              </div>
            )}

            <div className="overflow-hidden rounded-xl border border-border">
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-border bg-slate-50/80 text-xs uppercase text-slate-500">
                    <th className="px-4 py-2 font-semibold">Check</th>
                    <th className="px-4 py-2 font-semibold">Status</th>
                    <th className="px-4 py-2 font-semibold">Detail</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {(deployChecks.checks || []).map((check) => (
                    <tr key={check.id}>
                      <td className="px-4 py-2.5 font-medium text-ink">{check.label}</td>
                      <td className="px-4 py-2.5">
                        <span
                          className={`badge ${check.ok ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'}`}
                        >
                          {check.ok ? 'OK' : 'FAIL'}
                        </span>
                      </td>
                      <td className="px-4 py-2.5 text-xs text-slate-500">{check.detail || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {deployChecks.timestamp && (
              <p className="mt-3 text-xs text-slate-500">
                Checked at {new Date(deployChecks.timestamp).toLocaleString()}
              </p>
            )}
          </div>
        )}
      </section>
    </AdminPageShell>
  );
}
