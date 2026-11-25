import { Link, useForm } from '@inertiajs/react';
import { Check, Eye, Gift, Users } from 'lucide-react';
import PrimaryButton from '@/Components/buttons/primary-button';
import StatCard from '@/Components/cms/dashboard/stat-card';
import { UserCell } from '@/Components/ui/avatar';
import Badge from '@/Components/ui/badge';
import { EmptyRow, Stepper } from '@/Components/ui/controls';
import Pagination from '@/Components/ui/pagination';
import CmsLayout from '@/Layouts/cms-layout';
import { formatNumber, maskPhone, percentChange, relativeDay } from '@/lib/format';
import type { Paginated } from '@/types';

type UserRow = {
    id: number;
    name: string;
    phone: string | null;
    visits: number;
    points_earned: number;
    balance: number;
    last_check_in: string | null;
};

type Props = {
    users: Paginated<UserRow>;
    filters: { search: string };
    stats: {
        issued: number;
        issued_this_week: number;
        outstanding: number;
        unspent_percent: number | null;
        check_ins_this_month: number;
        check_ins_change: number | null;
    };
    pointsPerVisit: number;
    perkAt: number;
    rewardsOpen: boolean;
};

const PATH = '/cms/loyalty-points';

export default function Loyalty({ users, filters, stats, pointsPerVisit, perkAt, rewardsOpen }: Props) {
    const form = useForm({ points_per_visit: pointsPerVisit, perk_at: perkAt });
    const dirty = form.data.points_per_visit !== pointsPerVisit || form.data.perk_at !== perkAt;

    return (
        <CmsLayout title="Loyalty points" search={{ placeholder: 'Search users', value: filters.search, path: PATH }}>
            <section className="stat-grid stat-grid-3">
                <StatCard icon={Gift} tone="yellow" label="Points issued" value={formatNumber(stats.issued)} note={`+${formatNumber(stats.issued_this_week)} this week`} />
                <StatCard icon={Users} tone="plum" label="Outstanding balance" value={formatNumber(stats.outstanding)} note={stats.unspent_percent === null ? 'Nothing issued yet' : `${stats.unspent_percent}% unspent`} />
                <StatCard
                    icon={Check}
                    tone="green"
                    label="Check-ins this month"
                    value={formatNumber(stats.check_ins_this_month)}
                    note={percentChange(stats.check_ins_change) ?? 'No data last month'}
                    noteTone={stats.check_ins_change !== null && stats.check_ins_change < 0 ? 'danger' : 'success'}
                />
            </section>

            <section className="two-col">
                <form
                    className="card"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`${PATH}/rules`, { preserveScroll: true });
                    }}
                >
                    <h2 className="card-title">Points per confirmed visit</h2>
                    <p className="muted small mt-2 mb-4">Funded by Najik, not by vendors.</p>
                    <div className="rule-row">
                        <Stepper value={form.data.points_per_visit} onChange={(v) => form.setData('points_per_visit', v)} min={0} max={500} step={5} />
                        <span className="muted small">First perk at</span>
                        <Stepper value={form.data.perk_at} onChange={(v) => form.setData('perk_at', v)} min={20} max={5000} step={20} suffix="pts" />
                    </div>
                    {(form.errors.points_per_visit || form.errors.perk_at) && <p className="field-error mt-2">{form.errors.points_per_visit || form.errors.perk_at}</p>}
                    {dirty && (
                        <div className="mt-4 flex gap-3">
                            <PrimaryButton type="submit" size="medium" disabled={form.processing}>
                                Save
                            </PrimaryButton>
                            <PrimaryButton variant="outline" size="medium" onClick={() => form.reset()}>
                                Discard
                            </PrimaryButton>
                        </div>
                    )}
                </form>

                <div className="card">
                    <h2 className="card-title">Convert points to rewards</h2>
                    <p className="muted small mt-2 mb-4">Free services or other rewards, planned for later.</p>
                    <div className="flex items-center gap-3">
                        <Badge tone={rewardsOpen ? 'success' : 'yellow'}>{rewardsOpen ? 'Open' : 'Not open yet'}</Badge>
                        <PrimaryButton variant="outline" size="medium" disabled title="Rewards conversion is planned for a later release">
                            Plan conversion
                        </PrimaryButton>
                    </div>
                </div>
            </section>

            <section className="card">
                <div className="card-head">
                    <h2 className="card-title">User balances</h2>
                </div>
                <div className="table-scroll">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Phone</th>
                                <th>Visits</th>
                                <th>Points earned</th>
                                <th>Balance</th>
                                <th>Last check-in</th>
                                <th className="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 && <EmptyRow colSpan={7}>{filters.search ? 'No users match your search.' : 'No customers yet.'}</EmptyRow>}
                            {users.data.map((u) => (
                                <tr key={u.id}>
                                    <td>
                                        <UserCell name={u.name} />
                                    </td>
                                    <td>{maskPhone(u.phone)}</td>
                                    <td>{u.visits}</td>
                                    <td>{formatNumber(u.points_earned)}</td>
                                    <td>{formatNumber(u.balance)}</td>
                                    <td>{u.last_check_in ? relativeDay(u.last_check_in) : '—'}</td>
                                    <td className="text-right">
                                        <Link href={`${PATH}/${u.id}`} className="icon-action" aria-label={`View ${u.name}`}>
                                            <Eye size={18} />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination page={users} />
            </section>
        </CmsLayout>
    );
}
