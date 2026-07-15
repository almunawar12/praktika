import { Form, Head, Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type ForgotPasswordProps = {
    status: string | null;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    return (
        <AuthLayout
            title="Lupa Kata Sandi"
            description="Kirim tautan reset kata sandi ke email Anda"
        >
            <Head title="Lupa Kata Sandi" />

            {status ? (
                <Alert>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            ) : null}

            <Form
                action="/forgot-password"
                method="post"
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="username"
                                autoFocus
                                required
                                aria-invalid={Boolean(errors.email)}
                            />
                            {errors.email ? (
                                <p className="text-sm text-destructive">
                                    {errors.email}
                                </p>
                            ) : null}
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing ? (
                                <LoaderCircle
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            ) : null}
                            Kirim Tautan Reset
                        </Button>
                    </>
                )}
            </Form>

            <p className="text-center text-sm text-muted-foreground">
                <Link
                    href="/login"
                    className="underline-offset-4 hover:underline"
                >
                    Kembali ke halaman masuk
                </Link>
            </p>
        </AuthLayout>
    );
}
