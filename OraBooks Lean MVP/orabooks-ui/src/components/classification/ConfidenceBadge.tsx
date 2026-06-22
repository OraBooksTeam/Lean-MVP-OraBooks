type ConfidenceBadgeProps = {
  value: number;
  threshold?: number;
};

export default function ConfidenceBadge({ value, threshold = 70 }: ConfidenceBadgeProps) {
  const low = value < threshold;
  return (
    <span
      className={`badge border font-mono ${low ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
      title={low ? 'Low confidence. Please verify.' : 'High confidence suggestion.'}
    >
      {Number(value).toFixed(1)}% {low ? 'Low' : 'High'}
    </span>
  );
}
