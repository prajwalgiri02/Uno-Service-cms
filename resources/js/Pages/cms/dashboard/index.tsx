import { BarChart3, Bell, Clock, Flag, Inbox, MessageSquare, Users, Zap } from 'lucide-react';
import type { ReactNode } from 'react';
import OverviewChart from '@/Components/cms/dashboard/overview-chart';
import StatCard from '@/Components/cms/dashboard/stat-card';
import { UserCell } from '@/Components/ui/avatar';
import Badge, { type BadgeTone } from '@/Components/ui/badge';
import { EmptyRow, FilterSelect } from '@/Components/ui/controls';
import Pagination from '@/Components/ui/pagination';
import CmsLayout from '@/Layouts/cms-layout';
import { formatNumber, pad2, percentChange, time, relativeDay } from '@/lib/format';
import { applyFilters } from '@/lib/query';
import type { Paginated } from '@/types';

type NudgeRow = {
    id: number;
    vendor: string | null;
    category: string | null;
    user: string | null;
    distance_m: number;
    result: string;
    combined: boolean;
    sent_at: string;
};

type DashboardProps = {
    period: number;
    filters: { category_id: number | null; result: string | null };
    stats: {
        active_vendors: number;
        new_vendors: number;
        active_wishlists: number;
        new_wishlists: number;
        nudges_sent: number;
        nudges_change: number | null;
        visit_rate: number | null;
        open_reports: number;
        auto_paused: number;
    };
    chart: { labels: string[]; nudges: number[]; check_ins: number[] };
    attention: {
        pending_vendors: number;
        open_reports: number;
        open_tickets: number;
        not_confirmed_today: number;
        alert_delivery_rate: number | null;
    };
    nudges: Paginated<NudgeRow>;
    categories: { id: number; name: string }[];
    results: string[];
};

const RESULT_TONE: Record<string, BadgeTone> = { visited: 'success', opened: 'plum', sent: 'yellow', ignored: 'muted' };

const PATH = '/cms/dashboard';

function Dashboard({ period, filters, stats, chart, attention, nudges, categories, results }: DashboardProps) {
    const current = { period, category_id: filters.category_id ?? undefined, result: filters.result ?? undefined };
    const exportUrl = `${PATH}/export?${new URLSearchParams(Object.entries(current).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])).toString()}`;

    const attentionItems = [
        { icon: Inbox, tone: 'yellow', label: 'Vendors awaiting approval', value: String(attention.pending_vendors), href: '/cms/vendor-approvals' },
        { icon: Flag, tone: 'red', label: 'Open reports', value: String(attention.open_reports), href: '/cms/report-queue' },
        { icon: MessageSquare, tone: 'plum', label: 'Support tickets', value: String(attention.open_tickets), href: '/cms/support-desk' },
        { icon: Clock, tone: 'yellow', label: 'Not confirmed “open today”', value: String(attention.not_confirmed_today), href: '/cms/vendor-approvals?status=approved' },
        { icon: Bell, tone: 'plum', label: 'Alert delivery rate', value: attention.alert_delivery_rate === null ? '—' : `${attention.alert_delivery_rate}%` },
    ] as const;

    return (
        <>
            <div className="toolbar-row">
                <label className="inline-label">
                    Sort by
                    <FilterSelect value={period} onChange={(e) => applyFilters(PATH, current, { period: e.target.value })}>
                        <option value={7}>Last 7 days</option>
                        <option value={30}>Last 30 days</option>
                    </FilterSelect>
                </label>
                <a className="btn btn-primary btn-medium" href={exportUrl}>
                    Export
                </a>
            </div>

            <section className="stat-grid stat-grid-5">
                <StatCard icon={Inbox} tone="plum" label="Active vendors" value={formatNumber(stats.active_vendors)} note={`+${stats.new_vendors} approved in ${period} days`} />
                <StatCard icon={Users} tone="yellow" label="Active wishlists" value={formatNumber(stats.active_wishlists)} note={`+${stats.new_wishlists} added in ${period} days`} />
                <StatCard icon={Zap} tone="green" label="Nudges sent" value={formatNumber(stats.nudges_sent)} note={percentChange(stats.nudges_change) ?? 'No earlier data'} noteTone={stats.nudges_change !== null && stats.nudges_change < 0 ? 'danger' : 'success'} />
                <StatCard icon={BarChart3} tone="plum" label="Nudge → visit" value={stats.visit_rate === null ? '—' : `${stats.visit_rate}%`} note="Target 10%+" noteTone={stats.visit_rate !== null && stats.visit_rate < 10 ? 'danger' : 'success'} />
                <StatCard icon={Flag} tone="red" label="Open reports" value={pad2(stats.open_reports)} note={stats.auto_paused > 0 ? `${stats.auto_paused} auto-paused` : 'None auto-paused'} noteTone={stats.auto_paused > 0 ? 'danger' : 'muted'} />
            </section>

            <section className="dashboard-grid">
                <div className="card">
                    <div className="card-head">
                        <h2 className="card-title">Overview</h2>
                        <div className="legend">
                            <span>
                                <i className="dot plum" /> Nudges sent
                            </span>
                            <span>
                                <i className="dot yellow" /> Check-ins
                            </span>
                        </div>
                    </div>
                    <OverviewChart
                        labels={chart.labels}
                        series={[
                            { label: 'Nudges sent', values: chart.nudges, className: 'series-plum' },
                            { label: 'Check-ins', values: chart.check_ins, className: 'series-yellow' },
                        ]}
                    />
                </div>

                <div className="card">
                    <h2 className="card-title mb-5">Needs attention</h2>
                    <div className="attention-list">
                        {attentionItems.map(({ icon: Icon, tone, label, value, ...rest }) => {
                            const inner = (
                                <>
                                    <span className={`stat-icon ${tone}`}>
                                        <Icon size={20} strokeWidth={1.75} />
                                    </span>
                                    <span>{label}</span>
                                    <span className="value">{value}</span>
                                </>
                            );
                            return 'href' in rest ? (
                                <a key={label} href={rest.href} className="attention-item link">
                                    {inner}
                                </a>
                            ) : (
                                <div key={label} className="attention-item">
                                    {inner}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            <section className="card">
                <div className="card-head">
                    <h2 className="card-title">Recent nudges</h2>
                    <div className="filters">
                        <FilterSelect value={filters.category_id ?? ''} onChange={(e) => applyFilters(PATH, current, { category_id: e.target.value })} aria-label="Category">
                            <option value="">Category</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </FilterSelect>
                        <FilterSelect value={filters.result ?? ''} onChange={(e) => applyFilters(PATH, current, { result: e.target.value })} aria-label="Result">
                            <option value="">Result</option>
                            {results.map((r) => (
                                <option key={r} value={r}>
                                    {r[0].toUpperCase() + r.slice(1)}
                                </option>
                            ))}
                        </FilterSelect>
                    </div>
                </div>
                <div className="table-scroll">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Nudge ID</th>
                                <th>Vendor</th>
                                <th>Category</th>
                                <th>User</th>
                                <th>Distance</th>
                                <th>Result</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            {nudges.data.length === 0 && <EmptyRow colSpan={7}>No nudges match these filters yet.</EmptyRow>}
                            {nudges.data.map((n) => (
                                <tr key={n.id}>
                                    <td>
                                        N-{n.id}
                                        {n.combined && <span className="muted"> · combined</span>}
                                    </td>
                                    <td>{n.vendor ?? '—'}</td>
                                    <td>{n.category ?? '—'}</td>
                                    <td>
                                        <UserCell name={n.user} />
                                    </td>
                                    <td>{n.distance_m} m</td>
                                    <td>
                                        <Badge tone={RESULT_TONE[n.result] ?? 'muted'}>{n.result}</Badge>
                                    </td>
                                    <td className="muted">{relativeDay(n.sent_at) === 'Today' ? time(n.sent_at) : relativeDay(n.sent_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={nudges} />
            </section>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <CmsLayout title="Dashboard">{page}</CmsLayout>;

export default Dashboard;
