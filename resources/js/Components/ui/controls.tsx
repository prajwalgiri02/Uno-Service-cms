import { Minus, Plus } from 'lucide-react';
import type { ReactNode, SelectHTMLAttributes } from 'react';

export function Toggle({ checked, onChange, label, disabled }: { checked: boolean; onChange: (value: boolean) => void; label?: ReactNode; disabled?: boolean }) {
    return (
        <label className={`toggle${disabled ? ' disabled' : ''}`}>
            <button type="button" role="switch" aria-checked={checked} className={`toggle-track${checked ? ' on' : ''}`} onClick={() => !disabled && onChange(!checked)} disabled={disabled}>
                <span className="toggle-thumb" />
            </button>
            {label && <span>{label}</span>}
        </label>
    );
}

export function Stepper({ value, onChange, min, max, step = 1, suffix }: { value: number; onChange: (value: number) => void; min: number; max: number; step?: number; suffix?: string }) {
    return (
        <div className="stepper">
            <button type="button" aria-label="Decrease" onClick={() => onChange(Math.max(min, value - step))} disabled={value <= min}>
                <Minus size={16} />
            </button>
            <span className="stepper-value">
                {value}
                {suffix && <small>{suffix}</small>}
            </span>
            <button type="button" aria-label="Increase" onClick={() => onChange(Math.min(max, value + step))} disabled={value >= max}>
                <Plus size={16} />
            </button>
        </div>
    );
}

export function FilterSelect({ children, ...rest }: SelectHTMLAttributes<HTMLSelectElement> & { children: ReactNode }) {
    return (
        <select className="filter-select" {...rest}>
            {children}
        </select>
    );
}

export function Tabs<T extends string>({ items, value, onChange }: { items: { value: T; label: string; count?: number }[]; value: T; onChange: (value: T) => void }) {
    return (
        <div className="tabs" role="tablist">
            {items.map((item) => (
                <button key={item.value} type="button" role="tab" aria-selected={item.value === value} className={`tab${item.value === value ? ' active' : ''}`} onClick={() => onChange(item.value)}>
                    {item.label}
                    {item.count !== undefined && <span className="tab-count">{item.count}</span>}
                </button>
            ))}
        </div>
    );
}

export function EmptyRow({ colSpan, children }: { colSpan: number; children: ReactNode }) {
    return (
        <tr>
            <td colSpan={colSpan} className="empty-row">
                {children}
            </td>
        </tr>
    );
}

export function Field({ label, error, children, hint }: { label: string; error?: string; children: ReactNode; hint?: string }) {
    return (
        <div className="form-group">
            <span className="form-label">{label}</span>
            {children}
            {hint && !error && <p className="field-hint">{hint}</p>}
            {error && <p className="field-error">{error}</p>}
        </div>
    );
}
