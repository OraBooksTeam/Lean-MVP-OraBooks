import { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, Search } from 'lucide-react';
import { cn } from '@/lib/utils';

export type SearchableSelectOption = {
  value: string;
  label: string;
  searchText?: string;
};

type SearchableSelectProps = {
  label?: string;
  value: string;
  onChange: (value: string) => void;
  options: SearchableSelectOption[];
  placeholder?: string;
  searchPlaceholder?: string;
  emptyMessage?: string;
  disabled?: boolean;
  ariaLabel?: string;
};

export default function SearchableSelect({
  label,
  value,
  onChange,
  options,
  placeholder = 'Select…',
  searchPlaceholder = 'Search…',
  emptyMessage = 'No results found',
  disabled = false,
  ariaLabel,
}: SearchableSelectProps) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  const selected = options.find((option) => option.value === value);

  const filtered = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return options;

    return options.filter((option) => {
      const haystack = option.searchText || `${option.value} ${option.label}`.toLowerCase();
      return haystack.includes(normalized);
    });
  }, [options, query]);

  useEffect(() => {
    if (!open) return;

    const onDocumentMouseDown = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false);
        setQuery('');
      }
    };

    document.addEventListener('mousedown', onDocumentMouseDown);
    searchRef.current?.focus();

    return () => document.removeEventListener('mousedown', onDocumentMouseDown);
  }, [open]);

  const id = label?.toLowerCase().replace(/\s+/g, '-');

  return (
    <div ref={containerRef} className="relative w-full">
      {label ? (
        <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-ink-secondary">
          {label}
        </label>
      ) : null}
      <button
        type="button"
        id={id}
        disabled={disabled}
        aria-expanded={open}
        aria-haspopup="listbox"
        aria-label={ariaLabel || label}
        onClick={() => {
          if (disabled) return;
          setOpen((current) => !current);
          if (open) setQuery('');
        }}
        className={cn(
          'flex w-full items-center justify-between rounded-lg border border-border bg-white px-3.5 py-2.5 text-left text-sm shadow-sm transition-all duration-200',
          'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20',
          disabled && 'cursor-not-allowed bg-slate-50 text-slate-500',
          selected ? 'text-ink' : 'text-slate-400'
        )}
      >
        <span className="truncate">{selected?.label || placeholder}</span>
        <ChevronDown className={cn('h-4 w-4 shrink-0 text-slate-400 transition-transform', open && 'rotate-180')} />
      </button>
      {open ? (
        <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-border bg-white shadow-lg">
          <div className="border-b border-border p-2">
            <div className="relative">
              <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input
                ref={searchRef}
                type="text"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={searchPlaceholder}
                className="w-full rounded-md border border-border py-2 pl-8 pr-2 text-sm text-ink focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/20"
              />
            </div>
          </div>
          <ul className="max-h-56 overflow-y-auto py-1" role="listbox">
            {filtered.length === 0 ? (
              <li className="px-3 py-2 text-sm text-slate-500">{emptyMessage}</li>
            ) : (
              filtered.map((option) => (
                <li key={option.value || '__empty__'} role="option" aria-selected={option.value === value}>
                  <button
                    type="button"
                    className={cn(
                      'w-full px-3 py-2 text-left text-sm transition hover:bg-accent/10',
                      option.value === value && 'bg-accent/10 font-medium text-accent'
                    )}
                    onClick={() => {
                      onChange(option.value);
                      setOpen(false);
                      setQuery('');
                    }}
                  >
                    {option.label}
                  </button>
                </li>
              ))
            )}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
