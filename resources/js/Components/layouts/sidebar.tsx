import { Link, router, usePage } from '@inertiajs/react';
import { CMS_SIGN_OUT_PATH, sidebar } from '@/lib/constants/sidebar';
import type { Badges } from '@/types';

function isActive(currentPath: string, path: string): boolean {
    return currentPath === path || currentPath.startsWith(`${path}/`);
}

export function NajikLogo() {
    return (
        <div className="sidebar-logo">
            najik
            <span className="logo-dot" aria-hidden="true" />
        </div>
    );
}

export default function Sidebar({ badges }: { badges: Badges }) {
    const { url } = usePage();
    const currentPath = url.split('?')[0];

    return (
        <aside className="sidebar" id="sidebar">
            <NajikLogo />
            <nav className="sidebar-nav" aria-label="Main">
                {sidebar.map(({ label, icon: Icon, path }) => {
                    if (path === CMS_SIGN_OUT_PATH) {
                        return (
                            <button key={label} type="button" className="nav-item" onClick={() => router.post(CMS_SIGN_OUT_PATH)}>
                                <Icon size={20} strokeWidth={1.75} />
                                <span>{label}</span>
                            </button>
                        );
                    }

                    const count = badges[path.replace('/cms/', '')] ?? 0;

                    return (
                        <Link key={label} href={path} className={`nav-item${isActive(currentPath, path) ? ' active' : ''}`}>
                            <Icon size={20} strokeWidth={1.75} />
                            <span>{label}</span>
                            {count > 0 && <span className="nav-badge">{count}</span>}
                        </Link>
                    );
                })}
            </nav>
        </aside>
    );
}
