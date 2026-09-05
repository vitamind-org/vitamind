import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@vitamind/ui/dialog';
import { FormEventHandler, ReactNode, useState } from 'react';
import { Button } from '@vitamind/ui/button';
import { LoaderCircle } from 'lucide-react';
import { useForm, usePage } from '@inertiajs/react';
import { Form, FormField, FormFields } from '@vitamind/ui/form';
import { Label } from '@vitamind/ui/label';
import { Input } from '@vitamind/ui/input';
import InputError from '@vitamind/ui/input-error';
import { Checkbox } from '@vitamind/ui/checkbox';
import { SharedData } from '@/types';
import { User } from '@/types/user';
import FormSuccessful from '@/components/form-successful';

export default function UserForm({ user, children }: { user?: User; children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const { roles = [] } = usePage<SharedData>().props;

  const form = useForm({
    name: user?.name || '',
    email: user?.email || '',
    password: '',
    is_admin: user?.is_admin ?? false,
    role: user?.roles ?? [],
  });

  const toggleRole = (key: string, checked: boolean) => {
    form.setData('role', checked ? [...form.data.role, key] : form.data.role.filter((r) => r !== key));
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    if (user) {
      form.patch(route('users.update', user.id));
      return;
    }

    form.post(route('users.store'), {
      onSuccess() {
        setOpen(false);
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{user ? `Edit ${user.name}` : 'Create user'}</DialogTitle>
          <DialogDescription className="sr-only">
            {user ? `Fill the form to edit ${user.name}` : 'Fill the form to create a new user'}
          </DialogDescription>
        </DialogHeader>
        <Form id="user-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="name">Name</Label>
              <Input type="text" id="name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <InputError message={form.errors.name} />
            </FormField>
            <FormField>
              <Label htmlFor="email">Email</Label>
              <Input type="email" id="email" name="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
              <InputError message={form.errors.email} />
            </FormField>
            <FormField>
              <Label htmlFor="password">Password</Label>
              <Input
                type="password"
                id="password"
                name="password"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
              />
              <InputError message={form.errors.password} />
            </FormField>
            <FormField>
              <div className="flex items-center gap-2">
                <Checkbox
                  id="is_admin"
                  checked={form.data.is_admin}
                  onCheckedChange={(checked) => form.setData('is_admin', checked === true)}
                />
                <Label htmlFor="is_admin">Admin (system-wide access)</Label>
              </div>
              <InputError message={form.errors.is_admin} />
            </FormField>
            <FormField>
              <Label>Roles</Label>
              <div className="flex flex-col gap-2">
                {roles.map((role) => (
                  <div key={role.key} className="flex items-center gap-2">
                    <Checkbox
                      id={`role-${role.key}`}
                      checked={form.data.role.includes(role.key)}
                      onCheckedChange={(checked) => toggleRole(role.key, checked === true)}
                    />
                    <Label htmlFor={`role-${role.key}`}>{role.title}</Label>
                  </div>
                ))}
              </div>
              <InputError message={form.errors.role} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter className="items-center">
          <DialogClose asChild>
            <Button type="button" variant="outline">
              Cancel
            </Button>
          </DialogClose>
          <Button form="create-user-form" type="button" onClick={submit} disabled={form.processing}>
            {form.processing && <LoaderCircle className="animate-spin" />}
            <FormSuccessful successful={form.recentlySuccessful} />
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
