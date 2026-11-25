const numberFormat = new Intl.NumberFormat('en-US');

export function formatNumber(value: number | null | undefined): string {
    return value === null || value === undefined ? '—' : numberFormat.format(value);
}

export function pad2(value: number): string {
    return value < 10 && value >= 0 ? `0${value}` : String(value);
}

export function initials(name: string | null | undefined): string {
    if (!name) return '··';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

export function maskPhone(phone: string | null | undefined): string {
    if (!phone) return '—';
    const digits = phone.replace(/^\+977/, '');
    return digits.length >= 6 ? `${digits.slice(0, 2)}•••• ${digits.slice(-4)}` : phone;
}

function startOfDay(date: Date): number {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
}

export function relativeDay(iso: string | null | undefined): string {
    if (!iso) return '—';
    const days = Math.round((startOfDay(new Date()) - startOfDay(new Date(iso))) / 86_400_000);
    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 30) return `${days} days ago`;
    return new Date(iso).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function time(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }).toLowerCase();
}

export function dateTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-US', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
}

export function hoursLeft(iso: string): { label: string; tone: 'success' | 'yellow' | 'danger' } {
    const hours = (new Date(iso).getTime() - Date.now()) / 3_600_000;
    if (hours <= 0) return { label: 'Overdue', tone: 'danger' };
    const rounded = Math.max(1, Math.floor(hours));
    return { label: `${rounded} h left`, tone: hours <= 4 ? 'yellow' : 'success' };
}

export function percentChange(value: number | null | undefined): string | undefined {
    if (value === null || value === undefined) return undefined;
    return `${value > 0 ? '+' : ''}${value}%`;
}
