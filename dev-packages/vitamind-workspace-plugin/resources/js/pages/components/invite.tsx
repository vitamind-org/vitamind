import { Button } from '@vitamind/ui/button';
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
import { Form, FormField, FormFields } from '@vitamind/ui/form';
import { Input } from '@vitamind/ui/input';
import InputError from '@vitamind/ui/input-error';
import { Label } from '@vitamind/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@vitamind/ui/select';
import { SharedData } from '@/types';
import { Workspace } from '@/types/workspace';
import { useForm, usePage } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

export default function Invite({ workspace, onInviteSent, children }: { workspace: Workspace; onInviteSent?: () => void; children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const { workspaceRoles = [] } = usePage<SharedData>().props;
  const form = useForm({
    email: '',
    role: 'none',
  });

  const handleOpenChange = (isOpen: boolean) => {
    if (!isOpen) {
      form.resetAndClearErrors();
    }
    setOpen(isOpen);
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/settings/workspaces/${workspace.id}/users`, {
      onSuccess: () => {
        handleOpenChange(false);
        if (onInviteSent) {
          onInviteSent();
        }
      },
    });
  };
  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Invite users to workspace</DialogTitle>
          <DialogDescription className="sr-only">Invite a new user to workspace</DialogDescription>
        </DialogHeader>
        <Form id="invite-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                name="email"
                type="email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
              />
              <InputError message={form.errors.email} />
            </FormField>
            <FormField>
              <Label htmlFor="role">Role</Label>
              <Select value={form.data.role} onValueChange={(value) => form.setData('role', value)}>
                <SelectTrigger id="role" name="role" className="w-full">
                  <SelectValue placeholder="Select a role" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="none">No role (member only)</SelectItem>
                    <SelectItem value="admin">Admin</SelectItem>
                    {workspaceRoles.map((role) => (
                      <SelectItem key={role.key} value={role.key}>
                        {role.title}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                </SelectContent>
              </Select>
              <InputError message={form.errors.role} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Close</Button>
          </DialogClose>
          <Button form="invite-form" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Invite
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
