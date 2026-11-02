import { Search, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { cn } from '../../lib/utils';
import '../../css/directories.css';

interface FilterOption {
    value: string;
    label: string;
}

interface FilterBarProps {
    searchPlaceholder?: string;
    q: string;
    departmentId: string | null;
    employmentType: string | null;
    status: string | null;
    sort: string | null;
    departments: { id: number; name: string }[];
    types?: FilterOption[];
    statuses?: FilterOption[];
    sortOptions?: FilterOption[];
    onChange: (patch: Partial<FilterBarProps>) => void;
}

function SelectField({
    label,
    value,
    options,
    onChange,
    emptyLabel,
}: {
    label: string;
    value: string | null;
    options: FilterOption[];
    onChange: (value: string) => void;
    emptyLabel: string;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <label className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                {label}
            </label>
            <div className="relative">
                <select
                    aria-label={label}
                    className={cn(
                        'block w-full appearance-none rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition-colors outline-none',
                        'focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:ring-offset-1',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                    )}
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                >
                    <option value="">{emptyLabel}</option>
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
                <ChevronDown
                    className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-400"
                    aria-hidden="true"
                />
            </div>
        </div>
    );
}

export default function FilterBar({
    searchPlaceholder = 'Cari judul atau lokasi…',
    q,
    departmentId,
    employmentType,
    status,
    sort,
    departments,
    types,
    statuses,
    sortOptions,
    onChange: commit,
}: FilterBarProps) {
    const [draftQ, setDraftQ] = useState(q);
    const onChange = (patch: Partial<FilterBarProps>) => commit({ q: draftQ, ...patch });
    const hasActive = q || departmentId || employmentType || status || (sort && sort !== 'latest');

    return (
        <div className="ep-filter-bar rounded-md border border-slate-200 bg-white p-3 md:p-4">
            <form
                className="flex max-w-xs flex-1 gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    onChange({ q: draftQ });
                }}
            >
                <div className="relative flex-1">
                    <Search
                        className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        aria-label={searchPlaceholder}
                        className={cn(
                            'rounded-md border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm text-slate-700 transition-colors outline-none',
                            'focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:ring-offset-1',
                        )}
                        placeholder={searchPlaceholder}
                        value={draftQ}
                        onChange={(e) => setDraftQ(e.target.value)}
                    />
                </div>
                <button type="submit" className="ep-primary">
                    Cari
                </button>
            </form>

            {departments.length > 0 && (
                <SelectField
                    label="Departemen"
                    value={departmentId}
                    options={departments.map((d) => ({ value: String(d.id), label: d.name }))}
                    onChange={(v) => onChange({ departmentId: v || null })}
                    emptyLabel="Semua departemen"
                />
            )}

            {types && types.length > 0 && (
                <SelectField
                    label="Jenis kerja"
                    value={employmentType}
                    options={types}
                    onChange={(v) => onChange({ employmentType: v || null })}
                    emptyLabel="Semua jenis kerja"
                />
            )}
            {statuses && statuses.length > 0 && (
                <SelectField
                    label="Status lowongan"
                    value={status}
                    options={statuses ?? []}
                    onChange={(v) => onChange({ status: v || null })}
                    emptyLabel="Semua status"
                />
            )}

            {sortOptions && sortOptions.length && (
                <SelectField
                    label="Urutan"
                    value={sort}
                    options={sortOptions}
                    onChange={(v) => onChange({ sort: v || null })}
                    emptyLabel="Urutan"
                />
            )}

            {hasActive && (
                <button
                    type="button"
                    onClick={() =>
                        onChange({
                            q: '',
                            departmentId: null,
                            employmentType: null,
                            status: null,
                            sort: 'latest',
                        })
                    }
                    className={cn(
                        'inline-flex items-center gap-2 rounded-md border border-transparent bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition-colors outline-none',
                        'hover:bg-red-100 focus:ring-2 focus:ring-red-500 focus:ring-offset-1',
                    )}
                >
                    <span className="size-3.5 rounded-full bg-red-700" aria-hidden="true" />
                    Atur ulang
                </button>
            )}
        </div>
    );
}
