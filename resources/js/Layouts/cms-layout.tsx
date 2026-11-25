import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, Menu, Search } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Toaster, toast } from 'sonner';
import Sidebar from '@/Components/layouts/sidebar';
import type { PageProps } from '@/types';

export type HeaderSearch = {
    placeholder: string;
    value: string;
    path: string;
    params?: Record<string, string | number | undefined>;
};

type CmsLayoutProps = {
    title: string;
    backHref?: string;
    search?: HeaderSearch;
    actions?: ReactNode;
    children: ReactNode;
};

const ATTENTION_LABELS: Record<string, string> = {
    'vendor-approvals': 'Vendors awaiting approval',
    'report-queue': 'Open reports',
    'support-desk': 'Open support tickets',
};

function HeaderSearchBox({ search }: { search: HeaderSearch }) {
    const [value, setValue] = useState(search.value);
    const first = useRef(true);

    useEffect(() => setValue(search.value), [search.value]);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const id = window.setTimeout(() => {
            if (value === search.value) return;
            const params = Object.fromEntries(Object.entries({ ...search.params, search: value || undefined }).filter(([, v]) => v !== undefined && v !== ''));
            router.get(search.path, params as Record<string, string>, { preserveState: true, replace: true });
        }, 350);
        return () => window.clearTimeout(id);
    }, [value]);

    return (
        <label className="search-box">
            <Search size={18} />
            <input type="search" value={value} placeholder={search.placeholder} onChange={(e) => setValue(e.target.value)} aria-label={search.placeholder} />
        </label>
    );
}

function NotificationBell() {
    const { props } = usePage<PageProps>();
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const items = Object.entries(props.badges ?? {}).filter(([, count]) => count > 0);

    useEffect(() => {
        if (!open) return;
        const close = (e: MouseEvent) => !ref.current?.contains(e.target as Node) && setOpen(false);
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    return (
        <div className="bell-wrap" ref={ref}>
            <button type="button" className="icon-btn" aria-label="Notifications" aria-expanded={open} onClick={() => setOpen((v) => !v)}>
                <Bell size={20} strokeWidth={1.75} />
                {items.length > 0 && <span className="bell-dot" />}
            </button>
            {open && (
                <div className="popover">
                    <p className="popover-title">Needs attention</p>
                    {items.length === 0 ? (
                        <p className="popover-empty">All clear. Nothing is waiting on you.</p>
                    ) : (
                        items.map(([key, count]) => (
                            <Link key={key} href={`/cms/${key}`} className="popover-item" onClick={() => setOpen(false)}>
                                <span>{ATTENTION_LABELS[key] ?? key}</span>
                                <strong>{count}</strong>
                            </Link>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

export default function CmsLayout({ title, backHref, search, actions, children }: CmsLayoutProps) {
    const { url, props } = usePage<PageProps>();
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => setMenuOpen(false), [url]);

    useEffect(() => {
        if (props.flash?.success) toast.success(props.flash.success);
        if (props.flash?.error) toast.error(props.flash.error);
    }, [props.flash?.flash_id]);

    return (
        <div className={`app-shell${menuOpen ? ' show-sidebar' : ''}`}>
            <Head title={title} />
            <Sidebar badges={props.badges ?? {}} />
            <div className="sidebar-backdrop" onClick={() => setMenuOpen(false)} aria-hidden="true" />
            <main className="main">
                <header className="topbar">
                    <button type="button" className="menu-btn" aria-label="Open menu" onClick={() => setMenuOpen(true)}>
                        <Menu size={22} />
                    </button>
                    <div className="topbar-title">
                        {backHref && (
                            <Link href={backHref} className="back-link" aria-label="Back">
                                <ArrowLeft size={20} />
                            </Link>
                        )}
                        <h1>{title}</h1>
                    </div>
                    <div className="topbar-actions">
                        {actions}
                        {search && <HeaderSearchBox search={search} />}
                        <NotificationBell />
                    </div>
                </header>
                <div className="content">{children}</div>
            </main>
            <Toaster richColors position="bottom-right" />
        </div>
    );
}
