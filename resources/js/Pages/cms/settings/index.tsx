import { useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/buttons/primary-button';
import { UserCell } from '@/Components/ui/avatar';
import { Field } from '@/Components/ui/controls';
import CmsLayout from '@/Layouts/cms-layout';
import { relativeDay } from '@/lib/format';

type Props = {
    profile: { name: string | null; email: string | null };
    admins: { id: number; name: string | null; email: string | null; last_login_at: string | null }[];
};

export default function Settings({ profile, admins }: Props) {
    const profileForm = useForm({ name: profile.name ?? '', email: profile.email ?? '' });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
    const adminForm = useForm({ name: '', email: '', password: '' });

    return (
        <CmsLayout title="Settings">
            <section className="two-col">
                <form
                    className="card flex flex-col gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        profileForm.put('/cms/settings/profile', { preserveScroll: true });
                    }}
                >
                    <h2 className="card-title">Your profile</h2>
                    <Field label="Name" error={profileForm.errors.name}>
                        <input className="form-control" value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Email" error={profileForm.errors.email}>
                        <input className="form-control" type="email" value={profileForm.data.email} onChange={(e) => profileForm.setData('email', e.target.value)} required />
                    </Field>
                    <div>
                        <PrimaryButton type="submit" size="medium" disabled={profileForm.processing || !profileForm.isDirty}>
                            Save profile
                        </PrimaryButton>
                    </div>
                </form>

                <form
                    className="card flex flex-col gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        passwordForm.put('/cms/settings/password', { preserveScroll: true, onSuccess: () => passwordForm.reset() });
                    }}
                >
                    <h2 className="card-title">Change password</h2>
                    <Field label="Current password" error={passwordForm.errors.current_password}>
                        <input className="form-control" type="password" autoComplete="current-password" value={passwordForm.data.current_password} onChange={(e) => passwordForm.setData('current_password', e.target.value)} required />
                    </Field>
                    <Field label="New password" error={passwordForm.errors.password} hint="At least 10 characters with letters and numbers.">
                        <input className="form-control" type="password" autoComplete="new-password" value={passwordForm.data.password} onChange={(e) => passwordForm.setData('password', e.target.value)} required />
                    </Field>
                    <Field label="Repeat new password" error={passwordForm.errors.password_confirmation}>
                        <input className="form-control" type="password" autoComplete="new-password" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} required />
                    </Field>
                    <div>
                        <PrimaryButton type="submit" size="medium" disabled={passwordForm.processing}>
                            Change password
                        </PrimaryButton>
                    </div>
                </form>
            </section>

            <section className="two-col">
                <div className="card">
                    <div className="card-head">
                        <h2 className="card-title">Admins</h2>
                    </div>
                    <div className="table-scroll">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Last sign-in</th>
                                </tr>
                            </thead>
                            <tbody>
                                {admins.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            <UserCell name={a.name} />
                                        </td>
                                        <td>{a.email ?? '—'}</td>
                                        <td className="muted">{a.last_login_at ? relativeDay(a.last_login_at) : 'Never'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <form
                    className="card flex flex-col gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        adminForm.post('/cms/settings/admins', { preserveScroll: true, onSuccess: () => adminForm.reset() });
                    }}
                >
                    <h2 className="card-title">Add an admin</h2>
                    <p className="muted small">They sign in at /cms/login with this email and password.</p>
                    <Field label="Name" error={adminForm.errors.name}>
                        <input className="form-control" value={adminForm.data.name} onChange={(e) => adminForm.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Email" error={adminForm.errors.email}>
                        <input className="form-control" type="email" value={adminForm.data.email} onChange={(e) => adminForm.setData('email', e.target.value)} required />
                    </Field>
                    <Field label="Temporary password" error={adminForm.errors.password} hint="At least 10 characters with letters and numbers.">
                        <input className="form-control" type="password" autoComplete="new-password" value={adminForm.data.password} onChange={(e) => adminForm.setData('password', e.target.value)} required />
                    </Field>
                    <div>
                        <PrimaryButton type="submit" size="medium" disabled={adminForm.processing}>
                            Add admin
                        </PrimaryButton>
                    </div>
                </form>
            </section>
        </CmsLayout>
    );
}
