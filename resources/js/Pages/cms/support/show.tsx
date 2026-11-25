import { Link, router, useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/buttons/primary-button';
import Avatar from '@/Components/ui/avatar';
import Badge from '@/Components/ui/badge';
import { Field } from '@/Components/ui/controls';
import CmsLayout from '@/Layouts/cms-layout';
import { dateTime, hoursLeft, relativeDay } from '@/lib/format';

type Message = { id: number; from_admin: boolean; author: string; body: string; at: string };

type Ticket = {
    id: number;
    number: string;
    from: string | null;
    type: 'user' | 'vendor';
    subject: string;
    status: 'open' | 'resolved';
    opened_at: string;
    due_at: string;
    resolved_at: string | null;
    phone: string | null;
    user_id: number;
    messages: Message[];
};

export default function SupportTicket({ ticket }: { ticket: Ticket }) {
    const form = useForm({ body: '', resolve: false });
    const open = ticket.status === 'open';
    const left = hoursLeft(ticket.due_at);

    const send = (resolve: boolean) => {
        form.transform((data) => ({ ...data, resolve }));
        form.post(`/cms/support-desk/${ticket.id}/reply`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const setStatus = (status: 'open' | 'resolved') => router.patch(`/cms/support-desk/${ticket.id}/status`, { status }, { preserveScroll: true });

    return (
        <CmsLayout title={`${ticket.number} · ${ticket.subject}`} backHref="/cms/support-desk">
            <section className="dashboard-grid">
                <div className="card">
                    <div className="card-head">
                        <h2 className="card-title">Conversation</h2>
                        <Badge tone={open ? 'yellow' : 'success'}>{open ? 'Open' : 'Resolved'}</Badge>
                    </div>

                    <ol className="thread">
                        {ticket.messages.map((m) => (
                            <li key={m.id} className={`thread-message${m.from_admin ? ' from-admin' : ''}`}>
                                <div className="thread-meta">
                                    <strong>{m.author}</strong>
                                    <span className="muted small">{dateTime(m.at)}</span>
                                </div>
                                <p className="thread-body">{m.body}</p>
                            </li>
                        ))}
                    </ol>

                    <form
                        className="thread-reply"
                        onSubmit={(e) => {
                            e.preventDefault();
                            send(false);
                        }}
                    >
                        <Field label="Reply" error={form.errors.body} hint="The user gets a push notification with your reply.">
                            <textarea className="form-control textarea" rows={4} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} placeholder="Write a reply…" required />
                        </Field>
                        <div className="modal-actions">
                            {open && (
                                <PrimaryButton variant="outline" disabled={form.processing || form.data.body.trim() === ''} onClick={() => send(true)}>
                                    Reply and resolve
                                </PrimaryButton>
                            )}
                            <PrimaryButton type="submit" disabled={form.processing || form.data.body.trim() === ''}>
                                Send reply
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div className="stack">
                    <div className="card">
                        <div className="user-cell mb-4">
                            <Avatar name={ticket.from} />
                            <div>
                                <strong>{ticket.from ?? '—'}</strong>
                                <p className="muted small">{ticket.phone ?? '—'}</p>
                            </div>
                        </div>
                        <dl className="detail-grid one">
                            <div>
                                <dt>Type</dt>
                                <dd>
                                    <Badge tone={ticket.type === 'vendor' ? 'yellow' : 'plum'}>{ticket.type === 'vendor' ? 'Vendor' : 'User'}</Badge>
                                </dd>
                            </div>
                            <div>
                                <dt>Opened</dt>
                                <dd>
                                    {relativeDay(ticket.opened_at)} · {dateTime(ticket.opened_at)}
                                </dd>
                            </div>
                            <div>
                                <dt>{open ? 'Time left' : 'Resolved'}</dt>
                                <dd>{open ? <Badge tone={left.tone}>{left.label}</Badge> : dateTime(ticket.resolved_at)}</dd>
                            </div>
                        </dl>
                        <div className="action-stack">
                            {open ? (
                                <PrimaryButton block variant="outline" onClick={() => setStatus('resolved')}>
                                    Mark resolved
                                </PrimaryButton>
                            ) : (
                                <PrimaryButton block variant="outline" onClick={() => setStatus('open')}>
                                    Reopen ticket
                                </PrimaryButton>
                            )}
                            <Link href={`/cms/loyalty-points/${ticket.user_id}`} className="btn btn-outline btn-large btn-block">
                                View user and points
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </CmsLayout>
    );
}
