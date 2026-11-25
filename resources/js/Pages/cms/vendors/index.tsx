import { Link } from '@inertiajs/react';
import Badge, { type BadgeTone } from '@/Components/ui/badge';
import { EmptyRow, Tabs } from '@/Components/ui/controls';
import Pagination from '@/Components/ui/pagination';
import CmsLayout from '@/Layouts/cms-layout';
import { maskPhone, relativeDay } from '@/lib/format';
import { applyFilters } from '@/lib/query';
import type { Paginated } from '@/types';

type Status = 'pending' | 'approved' | 'paused' | 'rejected';

type ShopRow = {
    id: number;
    name: string;
    category: string | null;
    area: string | null;
    phone: string | null;
    status: string;
    status_label: string;
    submitted_at: string | null;
    auto_paused: boolean;
};

type Props = {
    shops: Paginated<ShopRow>;
    filters: { status: Status; search: string };
    counts: Record<Status, number>;
};

const PATH = '/cms/vendor-approvals';

const TONES: Record<string, BadgeTone> = {
    pending: 'yellow',
    changes_requested: 'plum',
    approved: 'success',
    paused: 'danger',
    rejected: 'muted',
};

const TITLES: Record<Status, string> = {
    pending: 'Pending approval',
    approved: 'Approved vendors',
    paused: 'Paused vendors',
    rejected: 'Rejected vendors',
};

export default function VendorApprovals({ shops, filters, counts }: Props) {
    return (
        <CmsLayout title="Vendor approvals" search={{ placeholder: 'Search vendors', value: filters.search, path: PATH, params: { status: filters.status } }}>
            <Tabs<Status>
                value={filters.status}
                onChange={(status) => applyFilters(PATH, filters, { status })}
                items={[
                    { value: 'pending', label: 'Pending', count: counts.pending },
                    { value: 'approved', label: 'Approved', count: counts.approved },
                    { value: 'paused', label: 'Paused', count: counts.paused },
                    { value: 'rejected', label: 'Rejected', count: counts.rejected },
                ]}
            />

            <section className="card">
                <div className="card-head">
                    <h2 className="card-title">{TITLES[filters.status]}</h2>
                </div>
                <div className="table-scroll">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Category</th>
                                <th>Area</th>
                                <th>Submitted</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th className="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {shops.data.length === 0 && <EmptyRow colSpan={7}>{filters.search ? 'No vendors match your search.' : 'Nothing here right now.'}</EmptyRow>}
                            {shops.data.map((shop) => (
                                <tr key={shop.id}>
                                    <td>{shop.name}</td>
                                    <td>{shop.category ?? '—'}</td>
                                    <td>{shop.area ?? '—'}</td>
                                    <td>{relativeDay(shop.submitted_at)}</td>
                                    <td>{maskPhone(shop.phone)}</td>
                                    <td>
                                        <Badge tone={TONES[shop.status] ?? 'muted'}>{shop.auto_paused ? 'Auto-paused' : shop.status_label}</Badge>
                                    </td>
                                    <td className="text-right">
                                        <Link href={`${PATH}/${shop.id}`} className="btn btn-outline btn-medium">
                                            Review
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={shops} />
            </section>
        </CmsLayout>
    );
}
