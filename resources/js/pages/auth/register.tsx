import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircleIcon, LockIcon, MailIcon } from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';
import InputError from '@vitamind/ui/input-error';
import TextLink from '@/components/text-link';
import IconBanner from '@/components/icon-banner';
import { Button } from '@vitamind/ui/button';
import { Input } from '@vitamind/ui/input';
import { Label } from '@vitamind/ui/label';
import AuthLayout from '@/layouts/auth/layout';
import { SharedData } from '@/types';
import { cn } from '@vitamind/ui/cn';

export default function Register() {
  const page = usePage<SharedData>();
  const pendingInvite = page.props.pendingInvite;
  const [emailLocked, setEmailLocked] = useState(!!pendingInvite);
  const emailInputRef = useRef<HTMLInputElement>(null);

  const unlockEmail = () => {
    setEmailLocked(false);
    // Wait for the readOnly attribute to actually drop off the input
    // before focusing it, otherwise the field would visibly stay
    // unfocusable for a frame — the focus is what makes "unlocked"
    // perceptible, since a readOnly input looks identical to an editable
    // one otherwise.
    requestAnimationFrame(() => {
      emailInputRef.current?.focus();
      emailInputRef.current?.select();
    });
  };

  const form = useForm({
    name: '',
    email: pendingInvite?.email || '',
    password: '',
    password_confirmation: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/register', {
      onFinish: () => form.reset('password', 'password_confirmation'),
    });
  };

  return (
    <AuthLayout title="Create your account" description="Enter your details below to register.">
      <Head title="Register" />

      {pendingInvite && (
        <IconBanner
          className="mb-6"
          icon={MailIcon}
          iconColor="#ebe8fd"
          circleClassName="bg-primary"
          tintClassName="border-primary/20 bg-primary/5"
          title={<>You&apos;re invited to {pendingInvite.workspaceName}</>}
          description={
            <>
              Register with <span className="text-foreground font-medium">{pendingInvite.email}</span> to join automatically.
            </>
          }
        />
      )}

      <form onSubmit={submit}>
        <div className="grid gap-6">
          <div className="grid gap-2">
            <Label htmlFor="name">Name</Label>
            <Input
              id="name"
              type="text"
              required
              autoFocus
              tabIndex={1}
              autoComplete="name"
              value={form.data.name}
              onChange={(e) => form.setData('name', e.target.value)}
              placeholder="Name"
            />
            <InputError message={form.errors.name} />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="email">Email address</Label>
            <Input
              ref={emailInputRef}
              id="email"
              type="email"
              required
              tabIndex={2}
              autoComplete="email"
              value={form.data.email}
              onChange={(e) => form.setData('email', e.target.value)}
              placeholder="email@example.com"
              readOnly={emailLocked}
              className={cn(emailLocked && 'bg-muted text-muted-foreground cursor-default')}
            />
            <InputError message={form.errors.email} />
            {emailLocked && (
              <button
                type="button"
                className="text-muted-foreground hover:text-foreground flex w-fit items-center gap-1 text-xs underline"
                onClick={unlockEmail}
              >
                <LockIcon className="size-3" />
                Use a different email
              </button>
            )}
          </div>

          <div className="grid gap-2">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              type="password"
              required
              tabIndex={3}
              autoComplete="new-password"
              value={form.data.password}
              onChange={(e) => form.setData('password', e.target.value)}
              placeholder="Password"
            />
            <InputError message={form.errors.password} />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="password_confirmation">Confirm Password</Label>
            <Input
              id="password_confirmation"
              type="password"
              required
              tabIndex={4}
              autoComplete="new-password"
              value={form.data.password_confirmation}
              onChange={(e) => form.setData('password_confirmation', e.target.value)}
              placeholder="Confirm Password"
            />
            <InputError message={form.errors.password_confirmation} />
          </div>

          <div className="flex items-center justify-between">
            <TextLink href="/login" className="text-sm" tabIndex={6}>
              Already registered?
            </TextLink>
          </div>

          <Button type="submit" className="mt-4 w-full" tabIndex={5} disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Register
          </Button>
        </div>
      </form>
    </AuthLayout>
  );
}
