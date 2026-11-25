import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PrimaryButton from '@/Components/buttons/primary-button';
import FormInput from '@/Components/inputs/form-input';
import AuthLayout from '@/Layouts/auth-layout';

const SignIn = () => (
    <Form action="/cms/login" method="post" resetOnError={['password']} className="mt-8 flex flex-col gap-5" noValidate>
        {({ errors, processing }) => (
            <>
                <FormInput name="email" type="email" label="Email" autoComplete="email" placeholder="admin@najik.app" error={errors.email} autoFocus />
                <FormInput name="password" type="password" label="Password" autoComplete="current-password" error={errors.password} />
                <label className="checkbox">
                    <input type="checkbox" name="remember" value="1" />
                    Remember me
                </label>
                <PrimaryButton type="submit" block disabled={processing}>
                    {processing ? 'Signing in…' : 'Sign in'}
                </PrimaryButton>
            </>
        )}
    </Form>
);

SignIn.layout = (page: ReactNode) => (
    <AuthLayout title="Welcome back" subtitle="Sign in to the Najik admin console.">
        {page}
    </AuthLayout>
);

export default SignIn;
