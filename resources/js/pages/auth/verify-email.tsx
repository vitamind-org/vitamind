import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth/layout';

export default function VerifyEmail({ status }: { status?: string }) {
  const { post, processing } = useForm({});

  const submit = (e: FormEvent) => {
    e.preventDefault();
    post('/email/verification-notification');
  };

  return (
    <AuthLayout title="Verify your email" description="Before getting started, please verify your email address by clicking on the link we just emailed to you.">
      <Head title="Email Verification" />

      {status === 'verification-link-sent' && (
        <div className="text-success mb-4 text-sm font-medium text-center">
          A new verification link has been sent to the email address you provided during registration.
        </div>
      )}

      <form onSubmit={submit}>
        <div className="grid gap-6">
          <Button type="submit" className="w-full" disabled={processing}>
            {processing && <LoaderCircleIcon className="animate-spin" />}
            Resend Verification Email
          </Button>

          <div className="flex justify-center">
            <Link
              href={route('logout')}
              method="post"
              as="button"
              className="text-muted-foreground hover:text-foreground text-sm underline"
            >
              Log Out
            </Link>
          </div>
        </div>
      </form>
    </AuthLayout>
  );
}
