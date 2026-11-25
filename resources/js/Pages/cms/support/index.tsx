import { Link } from '@inertiajs/react';
import { Check, Clock, MessageSquare } from 'lucide-react';
import StatCard from '@/Components/cms/dashboard/stat-card';
import { UserCell } from '@/Components/ui/avatar';
import Badge from '@/Components/ui/badge';
import { EmptyRow, FilterSelect } from '@/Components/ui/controls';
import Pagination from '@/Components/ui/pagination';
import CmsLayout from '@/Layouts/cms-layout';
import { dateTime, hoursLeft, pad2, relativeDay } from '@/lib/format';
import { applyFilters } from '@/lib/query';
import type { Paginated } from '@/types';

type TicketRow = {
    id: number;
    number: string;
    from: string | null;
    type: 'user' | 'vendor';
    subject: string;
    status: 'open' | 'resolved';
    opened_at: string;
    due_at: string;
    resolved_at: string | null;
};

type Props = {
    tickets: Paginated<TicketRow>;
    filters: { type: string; status: string; search: string };
    stats: { open: number; new_today: number; due_soon: number; avg_first_reply_hours: number | null };
};

const PATH = '/cms/support-desk';
const REPLY_TARGET_HOURS = 24;

export default function SupportDesk({ tickets, filters, stats }: Props) {
    const open = filters.status === 'open';
    const overTarget = stats.avg_first_reply_hours !== null && stats.avg_first_reply_hours > REPLY_TARGET_HOURS;

    return (
        <CmsLayout title="Support desk" search={{ placeholder: 'Search tickets', value: filters.search, path: PATH, params: { status: filters.status, type: filters.type || undefined } }}>
            <section className="stat-grid stat-grid-3">
                <StatCard icon={MessageSquare} tone="plum" label="Open tickets" value={pad2(stats.open)} note={`${stats.new_today} new today`} />
                <StatCard icon={Clock} tone="yellow" label="Due within 4 h" value={pad2(stats.due_soon)} note="24 h response target" noteTone={stats.due_soon > 0 ? 'danger' : 'muted'} />
                <StatCard
                    icon={Check}
                    tone="green"
                    label="Avg. first reply"
                    value={stats.avg_first_reply_hours === null ? '—' : `${stats.avg_first_reply_hours} h`}
                    note={stats.avg_first_reply_hours === null ? 'No replies yet' : overTarget ? 'Over target' : 'On track'}
                    noteTone={overTarget ? 'danger' : stats.avg_first_reply_hours === null ? 'muted' : 'success'}
                />
            </section>

            <section className="card">
                <div className="card-head">
                    <h2 className="card-title">Tickets</h2>
                    <div className="filters">
                        <FilterSelect value={filters.type} onChange={(e) => applyFilters(PATH, filters, { type: e.target.value })} aria-label="Type">
                            <option value="">Type</option>
                            <option value="user">User</option>
                            <option value="vendor">Vendor</option>
                        </FilterSelect>
                        <FilterSelect value={filters.status} onChange={(e) => applyFilters(PATH, filters, { status: e.target.value })} aria-label="Status">
                            <option value="open">Open</option>
                            <option value="resolved">Resolved</option>
                        </FilterSelect>
                    </div>
                </div>
                <div className="table-scroll">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>From</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Opened</th>
                                <th>{open ? 'Time left' : 'Resolved'}</th>
                                <th className="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tickets.data.length === 0 && <EmptyRow colSpan={7}>{open ? 'No open tickets. Inbox zero.' : 'No resolved tickets yet.'}</EmptyRow>}
                            {tickets.data.map((t) => {
                                const left = hoursLeft(t.due_at);
                                return (
                                    <tr key={t.id}>
                                        <td>{t.number}</td>
                                        <td>
                                            <UserCell name={t.from} />
                                        </td>
                                        <td>
                                            <Badge tone={t.type === 'vendor' ? 'yellow' : 'plum'}>{t.type === 'vendor' ? 'Vendor' : 'User'}</Badge>
                                        </td>
                                        <td>{t.subject}</td>
                                        <td>{relativeDay(t.opened_at)}</td>
                                        <td>{t.status === 'open' ? <Badge tone={left.tone}>{left.label}</Badge> : <span className="muted">{dateTime(t.resolved_at)}</span>}</td>
                                        <td className="text-right">
                                            <Link href={`${PATH}/${t.id}`} className="btn btn-outline btn-medium">
                                                Open
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <Pagination page={tickets} />
            </section>
        </CmsLayout>
    );
}
