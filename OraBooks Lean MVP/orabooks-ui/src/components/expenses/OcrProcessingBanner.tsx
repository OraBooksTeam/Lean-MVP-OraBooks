import { AlertTriangle, Loader2 } from 'lucide-react';

type OcrQueue = {
  status?: string;
  error_message?: string | null;
};

export default function OcrProcessingBanner({
  ocrConfidence,
  ocrQueue,
}: {
  ocrConfidence?: number | null;
  ocrQueue?: OcrQueue | null;
}) {
  if (ocrConfidence != null) {
    return null;
  }

  if (ocrQueue?.status === 'failed') {
    return (
      <div className="mt-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
        <div>
          <p className="font-semibold">OCR processing failed</p>
          <p className="mt-0.5">{ocrQueue.error_message || 'Try uploading the receipt again or enter fields manually.'}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="mt-4 flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50/80 p-4 text-sm text-blue-900">
      <Loader2 className="h-4 w-4 shrink-0 animate-spin" />
      <div>
        <p className="font-semibold">Extracting receipt fields</p>
        <p className="mt-0.5 text-blue-800/90">
          OCR is running in the background. This page refreshes automatically when fields are ready.
        </p>
      </div>
    </div>
  );
}
