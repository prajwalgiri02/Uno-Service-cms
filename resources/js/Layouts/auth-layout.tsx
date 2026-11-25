import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type AuthLayoutProps = {
    title: string;
    subtitle?: string;
    children: ReactNode;
};

export default function AuthLayout({ title, subtitle, children }: AuthLayoutProps) {
    return (
        <main className="auth-shell">
            <Head title={title} />
            <aside className="auth-brand">
                <div className="auth-rings" aria-hidden="true">
                    <span />
                    <span />
                    <span />
                    <i />
                </div>
                <h1>najik</h1>
                <p className="tagline">Keep every nudge useful and every vendor honest.</p>
                <p className="sub">Admin console for approvals, categories, reports and rules.</p>
            </aside>
            <section className="auth-form-wrap">
                <div className="auth-card">
                    <h2>{title}</h2>
                    {subtitle && <p className="subtitle">{subtitle}</p>}
                    {children}
                </div>
            </section>
        </main>
    );
}
