import { Link, useForm } from '@inertiajs/react';
import { Clock, Flag, Inbox } from 'lucide-react';
import { useState } from 'react';
import PrimaryButton from '@/Components/buttons/primary-button';
import StatCard from '@/Components/cms/dashboard/stat-card';
import { UserCell } from '@/Components/ui/avatar';
import Badge, { type BadgeTone } from '@/Components/ui/badge';
import { EmptyRow, Field, FilterSelect } from '@/Components/ui/controls';
import Modal from '@/Components/ui/modal';
import Pagination from '@/Components/ui/pagination';
import CmsLayout from '@/Layouts/cms-layout';
import { dateTime, pad2, relativeDay } from '@/lib/format';
import { applyFilters } from '@/lib/query';
import type { Paginated } from '@/types';

type ReportRow = {
    id: number;
    shop_id: number;
    vendor: string | null;
    reason: string;
    note: string | null;
    reported_by: string | null;
    created_at: string;
    reports: number;
    vendor_status: 'auto_paused' | 'paused' | 'under_review' | 'active';
    resolution: string | null;
    resolution_note: string | null;
    resolved_by: string | null;
    resolved_at: string | null;
};

type Props = {
    reports: Paginated<ReportRow>;
    filters: { status: string; search: string };
    stats: { open: number; auto_paused: number; avg_resolution_hours: number | null };
    resolutions: { value: string; label: string }[];
};

const PATH = '/cms/report-queue';

const VENDOR_STATUS: Record<ReportRow['vendor_status'], { label: string; tone: BadgeTone }> = {
    auto_paused: { label: 'Auto-paused', tone: 'danger' },
    paused: { label: 'Paused', tone: 'danger' },
    under_review: { label: 'Under review', tone: 'yellow' },
    active: { label: 'Active', tone: 'success' },
};

export default function ReportQueue({ reports, filters, stats, resolutions }: Props) {
    const [resolving, setResolving] = useState<ReportRow | null>(null);
    const form = useForm({ resolution: resolutions[0]?.value ?? '', note: '' });
    const open = filters.status === 'open';

    const startResolve = (row: ReportRow) => {
        form.setData({ resolution: row.vendor_status === 'auto_paused' ? 'vendor_restored' : 'warned', note: '' });
        form.clearErrors();
        setResolving(row);
    };

    return (
        <CmsLayout title="Report queue" search={{ placeholder: 'Search reports', value: filters.search, path: PATH, params: { status: filters.status } }}>
            <section className="stat-grid stat-grid-3">
                <StatCard icon={Flag} tone="red" label="Open reports" value={pad2(stats.open)} note={stats.auto_paused > 0 ? `${stats.auto_paused} vendor auto-paused` : 'No vendor auto-paused'} noteTone={stats.auto_paused > 0 ? 'danger' : 'muted'} />
                <StatCard icon={Inbox} tone="yellow" label="Auto-paused vendors" value={pad2(stats.auto_paused)} note="Paused automatically by the report rule" noteTone="muted" />
                <StatCard
                    icon={Clock}
                    tone="plum"
                    label="Avg. resolution"
                    value={stats.avg_resolution_hours === null ? '—' : `${stats.avg_resolution_hours} h`}
                    note="Target under 24 h"
                    noteTone={stats.avg_resolution_hours !== null && stats.avg_resolution_hours > 24 ? 'danger' : 'success'}
                />
            </section>

            <section className="card">
                <div className="card-head">
                    <h2 className="card-title">Reports</h2>
                    <FilterSelect value={filters.status} onChange={(e) => applyFilters(PATH, filters, { status: e.target.value })} aria-label="Status">
                        <option value="open">Open</option>
                        <option value="resolved">Resolved</option>
                    </FilterSelect>
                </div>
                <div className="table-scroll">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Reason</th>
                                <th>Reported by</th>
                                <th>Date</th>
                                <th>Reports</th>
                                <th>{open ? 'Vendor status' : 'Resolution'}</th>
                                <th className="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {reports.data.length === 0 && <EmptyRow colSpan={7}>{open ? 'No open reports. Nice.' : 'No resolved reports yet.'}</EmptyRow>}
                            {reports.data.map((r) => (
                                <tr key={r.id}>
                                    <td>
                                        <Link href={`/cms/vendor-approvals/${r.shop_id}`} className="link">
                                            {r.vendor ?? '—'}
                                        </Link>
                                    </td>
                                    <td>
                                        {r.reason}
                                        {r.note && <p className="muted small">{r.note}</p>}
                                    </td>
                                    <td>
                                        <UserCell name={r.reported_by} />
                                    </td>
                                    <td>{relativeDay(r.created_at)}</td>
                                    <td>{r.reports}</td>
                                    <td>
                                        {open ? (
                                            <Badge tone={VENDOR_STATUS[r.vendor_status].tone}>{VENDOR_STATUS[r.vendor_status].label}</Badge>
                                        ) : (
                                            <span className="small">
                                                {r.resolution}
                                                <span className="muted">
                                                    {' '}
                                                    · {r.resolved_by} · {dateTime(r.resolved_at)}
                                                </span>
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-right">
                                        {open && (
                                            <PrimaryButton variant="outline" size="medium" onClick={() => startResolve(r)}>
                                                Resolve
                                            </PrimaryButton>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={reports} />
            </section>

            <Modal open={resolving !== null} title={`Resolve reports · ${resolving?.vendor ?? ''}`} onClose={() => setResolving(null)}>
                {resolving && (
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`${PATH}/${resolving.id}/resolve`, { preserveScroll: true, onSuccess: () => setResolving(null) });
                        }}
                    >
                        <p className="muted small">
                            This resolves all {resolving.reports} open reports for this vendor together.
                        </p>
                        <Field label="Outcome" error={form.errors.resolution}>
                            <div className="radio-list">
                                {resolutions.map((r) => (
                                    <label key={r.value} className="radio">
                                        <input type="radio" name="resolution" value={r.value} checked={form.data.resolution === r.value} onChange={() => form.setData('resolution', r.value)} />
                                        {r.label}
                                    </label>
                                ))}
                            </div>
                        </Field>
                        <Field label="Note (optional)" error={form.errors.note}>
                            <textarea className="form-control textarea" rows={3} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                        </Field>
                        <div className="modal-actions">
                            <PrimaryButton variant="outline" onClick={() => setResolving(null)}>
                                Cancel
                            </PrimaryButton>
                            <PrimaryButton type="submit" disabled={form.processing}>
                                Resolve
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </CmsLayout>
    );
}
