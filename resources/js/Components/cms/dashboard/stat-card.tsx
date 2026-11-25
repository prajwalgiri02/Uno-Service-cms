import type { LucideIcon } from 'lucide-react';

type StatCardProps = {
    icon: LucideIcon;
    tone: 'plum' | 'yellow' | 'green' | 'red';
    label: string;
    value: string;
    note?: string;
    noteTone?: 'success' | 'danger' | 'muted';
};

export default function StatCard({ icon: Icon, tone, label, value, note, noteTone = 'success' }: StatCardProps) {
    return (
        <div className="stat-card">
            <span className={`stat-icon ${tone}`}>
                <Icon size={20} strokeWidth={1.75} />
            </span>
            <div>
                <p className="stat-label">{label}</p>
                <p className="stat-value">{value}</p>
                {note && <p className={`stat-note ${noteTone}`}>{note}</p>}
            </div>
        </div>
    );
}
