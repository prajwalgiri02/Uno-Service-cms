import type { ReactNode } from 'react';

export type BadgeTone = 'success' | 'danger' | 'plum' | 'yellow' | 'muted';

export default function Badge({ tone = 'plum', children }: { tone?: BadgeTone; children: ReactNode }) {
    return <span className={`badge badge-${tone}`}>{children}</span>;
}
