import { router, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/buttons/primary-button';
import Avatar from '@/Components/ui/avatar';
import Badge from '@/Components/ui/badge';
import { EmptyRow, Field } from '@/Components/ui/controls';
import CmsLayout from '@/Layouts/cms-layout';
import { dateTime, formatNumber, relativeDay } from '@/lib/format';

type Props = {
    member: { id: number; name: string; phone: string | null; area: string | null; balance: number; blocked: boolean; joined_at: string; errands: number };
    transactions: { id: number; points: number; reason: string; note: string | null; by: string | null; at: string }[];
};

export default function LoyaltyMember({ member, transactions }: Props) {
    const form = useForm({ points: '' as string | number, note: '' });

    return (
        <CmsLayout title={member.name} backHref="/cms/loyalty-points">
            <section className="dashboard-grid">
                <div className="card">
                    <div className="card-head">
                        <h2 className="card-title">Points history</h2>
                    </div>
                    <div className="table-scroll">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>What</th>
                                    <th>Points</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.length === 0 && <EmptyRow colSpan={4}>No points yet.</EmptyRow>}
                                {transactions.map((t) => (
                                    <tr key={t.id}>
                                        <td className="muted">{dateTime(t.at)}</td>
                                        <td>
                                            {t.reason}
                                            {t.note && <p className="muted small">{t.note}</p>}
                                        </td>
                                        <td className={t.points < 0 ? 'danger-text' : 'success-text'}>
                                            {t.points > 0 ? '+' : ''}
                                            {t.points}
                                        </td>
                                        <td className="muted">{t.by ?? 'App'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="stack">
                    <div className="card">
                        <div className="user-cell mb-4">
                            <Avatar name={member.name} />
                            <div>
                                <strong>{member.name}</strong>
                                <p className="muted small">{member.phone}</p>
                            </div>
                        </div>
                        <dl className="detail-grid one">
                            <div>
                                <dt>Balance</dt>
                                <dd className="big">{formatNumber(member.balance)} pts</dd>
                            </div>
                            <div>
                                <dt>Area</dt>
                                <dd>{member.area ?? '—'}</dd>
                            </div>
                            <div>
                                <dt>On their list</dt>
                                <dd>{member.errands} errands</dd>
                            </div>
                            <div>
                                <dt>Joined</dt>
                                <dd>{relativeDay(member.joined_at)}</dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd>
                                    <Badge tone={member.blocked ? 'danger' : 'success'}>{member.blocked ? 'Blocked' : 'Active'}</Badge>
                                </dd>
                            </div>
                        </dl>
                        <PrimaryButton
                            block
                            variant="outline"
                            className={member.blocked ? '' : 'danger-text'}
                            onClick={() => router.post(`/cms/loyalty-points/${member.id}/block`, {}, { preserveScroll: true })}
                        >
                            {member.blocked ? 'Unblock user' : 'Block user'}
                        </PrimaryButton>
                    </div>

                    <form
                        className="card flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/cms/loyalty-points/${member.id}/adjust`, { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                    >
                        <h2 className="card-title">Adjust points</h2>
                        <Field label="Points (use a minus sign to remove)" error={form.errors.points}>
                            <input className="form-control" type="number" value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} required />
                        </Field>
                        <Field label="Reason" error={form.errors.note} hint="Shown in the history, e.g. support ticket number.">
                            <input className="form-control" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} required />
                        </Field>
                        <PrimaryButton type="submit" disabled={form.processing}>
                            Save adjustment
                        </PrimaryButton>
                    </form>
                </div>
            </section>
        </CmsLayout>
    );
}
