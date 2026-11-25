import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CornerDownRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PrimaryButton from '@/Components/buttons/primary-button';
import Badge from '@/Components/ui/badge';
import { EmptyRow, Field, FilterSelect, Toggle } from '@/Components/ui/controls';
import Modal from '@/Components/ui/modal';
import CmsLayout from '@/Layouts/cms-layout';
import { formatNumber } from '@/lib/format';
import { applyFilters } from '@/lib/query';

type Row = {
    id: number;
    parent_id: number | null;
    name: string;
    name_ne: string | null;
    emoji: string | null;
    options: string[];
    keywords: string[];
    typical_price_min: number | null;
    typical_price_max: number | null;
    status: boolean;
    sort_order: number;
    vendors: number;
    wishlists: number;
};

type Props = {
    rows: Row[];
    mains: { id: number; name: string }[];
    filters: { search: string; status: string };
    requests: { term: string; total: number; last_at: string }[];
};

const PATH = '/cms/category-manager';

const empty = {
    name: '',
    name_ne: '',
    parent_id: '' as string | number,
    emoji: '',
    options: '',
    keywords: '',
    typical_price_min: '' as string | number,
    typical_price_max: '' as string | number,
    status: true,
    sort_order: 0 as string | number,
};

export default function Categories({ rows, mains, filters, requests }: Props) {
    const [editing, setEditing] = useState<Row | null>(null);
    const [open, setOpen] = useState(false);
    const [deleting, setDeleting] = useState<Row | null>(null);
    const form = useForm(empty);

    const openAdd = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(empty);
        setOpen(true);
    };

    const openEdit = (row: Row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            name: row.name,
            name_ne: row.name_ne ?? '',
            parent_id: row.parent_id ?? '',
            emoji: row.emoji ?? '',
            options: row.options.join(', '),
            keywords: row.keywords.join(', '),
            typical_price_min: row.typical_price_min ?? '',
            typical_price_max: row.typical_price_max ?? '',
            status: row.status,
            sort_order: row.sort_order,
        });
        setOpen(true);
    };

    const save = () => {
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        editing ? form.put(`${PATH}/${editing.id}`, options) : form.post(PATH, options);
    };

    return (
        <CmsLayout title="Category manager" search={{ placeholder: 'Search categories', value: filters.search, path: PATH, params: { status: filters.status || undefined } }}>
            <div className="banner">
                <AlertTriangle size={20} className="shrink-0 text-ink" />
                <div>
                    <strong>Any change here applies across the whole platform, with no app update.</strong>
                    Removing a category may leave users without a selected category. Wishlisting a main category also matches its subcategories.
                </div>
            </div>

            <div>
                <PrimaryButton onClick={openAdd}>
                    <Plus size={18} /> Add new category
                </PrimaryButton>
            </div>

            <section className="card">
                <div className="card-head">
                    <h2 className="card-title">Categories</h2>
                    <FilterSelect value={filters.status} onChange={(e) => applyFilters(PATH, filters, { status: e.target.value })} aria-label="Status">
                        <option value="">Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </FilterSelect>
                </div>
                <div className="table-scroll">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Name (English)</th>
                                <th>Name (नेपाली)</th>
                                <th>Level</th>
                                <th>Vendors</th>
                                <th>Wishlists</th>
                                <th>Status</th>
                                <th className="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && <EmptyRow colSpan={7}>No categories found.</EmptyRow>}
                            {rows.map((row) => (
                                <tr key={row.id}>
                                    <td>
                                        {row.parent_id ? (
                                            <span className="sub-name">
                                                <CornerDownRight size={14} /> {row.emoji} {row.name}
                                            </span>
                                        ) : (
                                            <span>
                                                {row.emoji} {row.name}
                                            </span>
                                        )}
                                    </td>
                                    <td>{row.name_ne ?? '—'}</td>
                                    <td>{row.parent_id ? 'Sub' : 'Main'}</td>
                                    <td>{formatNumber(row.vendors)}</td>
                                    <td>{formatNumber(row.wishlists)}</td>
                                    <td>
                                        <Badge tone={row.status ? 'success' : 'danger'}>{row.status ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td className="text-right">
                                        <div className="row-actions">
                                            <button type="button" className="icon-action" aria-label={`Edit ${row.name}`} onClick={() => openEdit(row)}>
                                                <Pencil size={16} />
                                            </button>
                                            <button type="button" className="icon-action danger" aria-label={`Delete ${row.name}`} onClick={() => setDeleting(row)}>
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            {requests.length > 0 && (
                <section className="card">
                    <h2 className="card-title mb-2">Asked for but not listed</h2>
                    <p className="muted small mb-4">People searched for these in the app and found nothing.</p>
                    <div className="badge-row">
                        {requests.map((r) => (
                            <Badge key={r.term} tone="yellow">
                                {r.term} · {r.total}
                            </Badge>
                        ))}
                    </div>
                </section>
            )}

            <Modal open={open} title={editing ? `Edit ${editing.name}` : 'Add new category'} onClose={() => setOpen(false)} width={600}>
                <form
                    className="form-grid"
                    onSubmit={(e) => {
                        e.preventDefault();
                        save();
                    }}
                >
                    <Field label="Name (English)" error={form.errors.name}>
                        <input className="form-control" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Name (नेपाली)" error={form.errors.name_ne}>
                        <input className="form-control" value={form.data.name_ne} onChange={(e) => form.setData('name_ne', e.target.value)} />
                    </Field>
                    <Field label="Parent" error={form.errors.parent_id} hint="Leave empty for a main category.">
                        <select className="form-control" value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value)}>
                            <option value="">None (main category)</option>
                            {mains
                                .filter((m) => m.id !== editing?.id)
                                .map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                        </select>
                    </Field>
                    <Field label="Emoji" error={form.errors.emoji}>
                        <input className="form-control" value={form.data.emoji} onChange={(e) => form.setData('emoji', e.target.value)} placeholder="💇" />
                    </Field>
                    <Field label="Options" error={form.errors.options} hint="Comma separated, e.g. men's cut, beard trim">
                        <input className="form-control" value={form.data.options} onChange={(e) => form.setData('options', e.target.value)} />
                    </Field>
                    <Field label="Search words" error={form.errors.keywords} hint="Comma separated, English and Nepali">
                        <input className="form-control" value={form.data.keywords} onChange={(e) => form.setData('keywords', e.target.value)} />
                    </Field>
                    <Field label="Typical price from (Rs)" error={form.errors.typical_price_min}>
                        <input className="form-control" type="number" min={0} value={form.data.typical_price_min} onChange={(e) => form.setData('typical_price_min', e.target.value)} />
                    </Field>
                    <Field label="Typical price to (Rs)" error={form.errors.typical_price_max}>
                        <input className="form-control" type="number" min={0} value={form.data.typical_price_max} onChange={(e) => form.setData('typical_price_max', e.target.value)} />
                    </Field>
                    <Field label="Sort order" error={form.errors.sort_order}>
                        <input className="form-control" type="number" min={0} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} />
                    </Field>
                    <Field label="Status">
                        <Toggle checked={form.data.status} onChange={(v) => form.setData('status', v)} label={form.data.status ? 'Active' : 'Inactive'} />
                    </Field>
                    <div className="modal-actions span-2">
                        <PrimaryButton variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </PrimaryButton>
                        <PrimaryButton type="submit" disabled={form.processing}>
                            {editing ? 'Save changes' : 'Add category'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal open={deleting !== null} title="Delete category" onClose={() => setDeleting(null)}>
                {deleting && (
                    <>
                        <p className="mb-2">
                            Delete <strong>{deleting.name}</strong>?
                        </p>
                        <p className="muted small mb-5">Categories used by vendors, wishlists or subcategories cannot be deleted. Switch them to inactive instead.</p>
                        <div className="modal-actions">
                            <PrimaryButton variant="outline" onClick={() => setDeleting(null)}>
                                Cancel
                            </PrimaryButton>
                            <PrimaryButton className="btn-danger" onClick={() => router.delete(`${PATH}/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}>
                                Delete
                            </PrimaryButton>
                        </div>
                    </>
                )}
            </Modal>
        </CmsLayout>
    );
}
