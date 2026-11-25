import { router, useForm } from '@inertiajs/react';
import { Check, Clock, MapPin, Store } from 'lucide-react';
import { useState } from 'react';
import PrimaryButton from '@/Components/buttons/primary-button';
import Badge from '@/Components/ui/badge';
import { Field } from '@/Components/ui/controls';
import Modal from '@/Components/ui/modal';
import CmsLayout from '@/Layouts/cms-layout';
import { dateTime, relativeDay } from '@/lib/format';

type Checklist = { check_photos: boolean; check_pin: boolean; check_phone: boolean; check_prices: boolean };
type Hours = Record<string, { open: string; close: string } | null> | null;

type Shop = {
    id: number;
    name: string;
    category: string | null;
    phone: string | null;
    owner: string | null;
    owner_phone: string | null;
    area: string | null;
    landmark: string | null;
    latitude: number;
    longitude: number;
    opening_hours: Hours;
    women_run: boolean;
    status: string;
    status_label: string;
    status_note: string | null;
    submitted_at: string | null;
    checklist: Checklist;
    photos: { id: number; url: string }[];
    services: { id: number; name: string; minutes: number; for_who: string | null; price_min: number; price_max: number }[];
    check_ins: number;
    open_reports: number;
    history: { id: number; to: string; by: string; note: string | null; at: string }[];
};

type Action = 'approve' | 'request_changes' | 'reject' | 'pause' | 'restore';

const CHECKS: { key: keyof Checklist; label: string }[] = [
    { key: 'check_photos', label: 'Photos match the shop' },
    { key: 'check_pin', label: 'Map pin matches the landmark' },
    { key: 'check_phone', label: 'Phone verified by call' },
    { key: 'check_prices', label: 'Price ranges are realistic' },
];

const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

const ACTION_COPY: Record<Action, { title: string; button: string; noteLabel: string; needsNote: boolean }> = {
    approve: { title: 'Approve vendor', button: 'Approve vendor', noteLabel: 'Note (optional)', needsNote: false },
    request_changes: { title: 'Request changes', button: 'Send request', noteLabel: 'What should the vendor change?', needsNote: true },
    reject: { title: 'Reject vendor', button: 'Reject', noteLabel: 'Reason shown to the vendor', needsNote: true },
    pause: { title: 'Pause vendor', button: 'Pause vendor', noteLabel: 'Reason shown to the vendor', needsNote: true },
    restore: { title: 'Restore vendor', button: 'Make live again', noteLabel: 'Note (optional)', needsNote: false },
};

function hoursSummary(hours: Hours): string {
    if (!hours) return 'Not set';
    return DAYS.map((d) => {
        const h = hours[d];
        return `${d[0].toUpperCase()}${d.slice(1)} ${h ? `${h.open}–${h.close}` : 'closed'}`;
    }).join(' · ');
}

export default function VendorReview({ shop }: { shop: Shop }) {
    const [action, setAction] = useState<Action | null>(null);
    const form = useForm({ note: '' });
    const live = ['pending', 'changes_requested', 'rejected'].includes(shop.status);

    const toggleCheck = (key: keyof Checklist) => {
        router.patch(`/cms/vendor-approvals/${shop.id}/checklist`, { [key]: !shop.checklist[key] }, { preserveScroll: true });
    };

    const submit = () => {
        if (!action) return;
        form.transform((data) => ({ ...data, action }));
        form.post(`/cms/vendor-approvals/${shop.id}/decision`, {
            preserveScroll: true,
            onSuccess: () => {
                setAction(null);
                form.reset();
            },
        });
    };

    const mapUrl = `https://www.openstreetmap.org/?mlat=${shop.latitude}&mlon=${shop.longitude}#map=18/${shop.latitude}/${shop.longitude}`;

    return (
        <CmsLayout title={`Review: ${shop.name}`} backHref="/cms/vendor-approvals">
            <section className="dashboard-grid">
                <div className="card">
                    <div className="card-head">
                        <h2 className="card-title">Shop details</h2>
                        <Badge tone={shop.status === 'approved' ? 'success' : shop.status === 'paused' ? 'danger' : 'yellow'}>{shop.status_label}</Badge>
                    </div>

                    <div className="photo-grid">
                        {shop.photos.length === 0
                            ? [0, 1, 2].map((i) => (
                                  <div key={i} className="photo-placeholder">
                                      <Store size={28} strokeWidth={1.5} />
                                  </div>
                              ))
                            : shop.photos.map((p) => (
                                  <a key={p.id} href={p.url} target="_blank" rel="noreferrer" className="photo">
                                      <img src={p.url} alt={`${shop.name} photo`} />
                                  </a>
                              ))}
                    </div>
                    {shop.photos.length === 0 && <p className="muted small mt-2">No photos uploaded yet.</p>}

                    <dl className="detail-grid">
                        <div>
                            <dt>Business name</dt>
                            <dd>{shop.name}</dd>
                        </div>
                        <div>
                            <dt>Category</dt>
                            <dd>{shop.category ?? '—'}</dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd>{shop.phone ?? '—'}</dd>
                        </div>
                        <div>
                            <dt>Landmark</dt>
                            <dd>{[shop.landmark, shop.area].filter(Boolean).join(', ') || '—'}</dd>
                        </div>
                        <div>
                            <dt>Opening hours</dt>
                            <dd>{hoursSummary(shop.opening_hours)}</dd>
                        </div>
                        <div>
                            <dt>Submitted</dt>
                            <dd>
                                {relativeDay(shop.submitted_at)} · by {shop.owner ?? 'owner'}
                            </dd>
                        </div>
                        <div>
                            <dt>Women-run</dt>
                            <dd>{shop.women_run ? 'Yes' : 'No'}</dd>
                        </div>
                        <div>
                            <dt>Activity</dt>
                            <dd>
                                {shop.check_ins} check-ins · {shop.open_reports} open reports
                            </dd>
                        </div>
                    </dl>

                    <h3 className="section-title">Services</h3>
                    <div className="table-scroll">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Duration</th>
                                    <th>For</th>
                                    <th>Price range</th>
                                </tr>
                            </thead>
                            <tbody>
                                {shop.services.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="empty-row">
                                            No services added yet.
                                        </td>
                                    </tr>
                                )}
                                {shop.services.map((s) => (
                                    <tr key={s.id}>
                                        <td>{s.name}</td>
                                        <td>{s.minutes} min</td>
                                        <td className="capitalize">{s.for_who ?? 'All'}</td>
                                        <td>
                                            Rs {s.price_min}–{s.price_max}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <a className="map-pin" href={mapUrl} target="_blank" rel="noreferrer">
                        <MapPin size={20} />
                        Map pin · {shop.area ?? `${shop.latitude.toFixed(5)}, ${shop.longitude.toFixed(5)}`} · open in map
                    </a>
                </div>

                <div className="stack">
                    <div className="card">
                        <h2 className="card-title mb-4">Verification checklist</h2>
                        <ul className="checklist">
                            {CHECKS.map(({ key, label }) => (
                                <li key={key}>
                                    <button type="button" className={`check${shop.checklist[key] ? ' done' : ''}`} onClick={() => toggleCheck(key)} aria-pressed={shop.checklist[key]}>
                                        {shop.checklist[key] ? <Check size={14} strokeWidth={3} /> : <Clock size={14} strokeWidth={2.5} />}
                                    </button>
                                    {label}
                                </li>
                            ))}
                        </ul>

                        <div className="divider" />

                        {shop.status_note && <p className="note-box">{shop.status_note}</p>}

                        <div className="action-stack">
                            {live && (
                                <>
                                    <PrimaryButton block onClick={() => setAction('approve')}>
                                        <Check size={16} /> Approve vendor
                                    </PrimaryButton>
                                    <PrimaryButton block variant="outline" onClick={() => setAction('request_changes')}>
                                        Request changes
                                    </PrimaryButton>
                                    {shop.status !== 'rejected' && (
                                        <PrimaryButton block variant="outline" className="danger-text" onClick={() => setAction('reject')}>
                                            Reject
                                        </PrimaryButton>
                                    )}
                                </>
                            )}
                            {shop.status === 'approved' && (
                                <PrimaryButton block variant="outline" className="danger-text" onClick={() => setAction('pause')}>
                                    Pause vendor
                                </PrimaryButton>
                            )}
                            {shop.status === 'paused' && (
                                <PrimaryButton block onClick={() => setAction('restore')}>
                                    Make live again
                                </PrimaryButton>
                            )}
                        </div>
                        <p className="muted small mt-3">A vendor goes live only after approval. Every decision is logged with your name.</p>
                    </div>

                    <div className="card">
                        <h2 className="card-title mb-4">History</h2>
                        <ul className="timeline">
                            {shop.history.map((h) => (
                                <li key={h.id}>
                                    <strong>{h.to}</strong> · {h.by}
                                    <span className="muted small"> · {dateTime(h.at)}</span>
                                    {h.note && <p className="muted small">{h.note}</p>}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </section>

            <Modal open={action !== null} title={action ? ACTION_COPY[action].title : ''} onClose={() => setAction(null)}>
                {action && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            submit();
                        }}
                        className="flex flex-col gap-4"
                    >
                        <Field label={ACTION_COPY[action].noteLabel} error={form.errors.note}>
                            <textarea className="form-control textarea" rows={4} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} required={ACTION_COPY[action].needsNote} />
                        </Field>
                        <div className="modal-actions">
                            <PrimaryButton variant="outline" onClick={() => setAction(null)}>
                                Cancel
                            </PrimaryButton>
                            <PrimaryButton type="submit" disabled={form.processing}>
                                {ACTION_COPY[action].button}
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </CmsLayout>
    );
}
