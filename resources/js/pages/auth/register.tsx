import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { FormEvent } from 'react';
import InputError from '@/components/ui/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth/layout';

export default function Register() {
  const form = useForm({
    name: '',
    email: '',
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
              id="email"
              type="email"
              required
              tabIndex={2}
              autoComplete="email"
              value={form.data.email}
              onChange={(e) => form.setData('email', e.target.value)}
              placeholder="email@example.com"
            />
            <InputError message={form.errors.email} />
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
