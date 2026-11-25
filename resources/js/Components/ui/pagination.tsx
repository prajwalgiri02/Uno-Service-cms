import { Link, router } from '@inertiajs/react';
import type { Paginated } from '@/types';

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

function label(raw: string): string {
    if (raw.includes('Previous')) return '‹';
    if (raw.includes('Next')) return '›';
    return raw;
}

export default function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.total === 0) return null;

    const changePerPage = (perPage: number) => {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', String(perPage));
        url.searchParams.delete('page');
        router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <div className="pagination">
            <span className="pagination-info">
                {page.from}–{page.to} of {page.total}
            </span>
            <label className="pagination-size">
                Rows
                <select value={page.per_page} onChange={(e) => changePerPage(Number(e.target.value))}>
                    {PER_PAGE_OPTIONS.map((n) => (
                        <option key={n} value={n}>
                            {n}
                        </option>
                    ))}
                </select>
            </label>
            {page.last_page > 1 && (
                <nav className="pagination-links" aria-label="Pages">
                    {page.links.map((link, i) =>
                        link.url ? (
                            <Link key={i} href={link.url} preserveScroll className={`page-link${link.active ? ' active' : ''}`}>
                                {label(link.label)}
                            </Link>
                        ) : (
                            <span key={i} className="page-link disabled">
                                {label(link.label)}
                            </span>
                        ),
                    )}
                </nav>
            )}
        </div>
    );
}
