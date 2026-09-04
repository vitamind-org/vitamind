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
import { WorkspaceUser } from '@/types/workspace-user';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

export default function RemoveUser({ workspaceId, user, children }: { workspaceId: number; user: WorkspaceUser; children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const form = useForm();

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.delete(`/settings/workspaces/${workspaceId}/users/${user.id}`, {
      onSuccess: () => {
        setOpen(false);
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Remove user</DialogTitle>
          <DialogDescription className="sr-only">Remove user from workspace.</DialogDescription>
        </DialogHeader>

        <p className="p-4">
          Are you sure you want to remove <b>{user.email}</b> from this workspace?
        </p>

        <DialogFooter className="gap-2">
          <DialogClose asChild>
            <Button variant="outline">Cancel</Button>
          </DialogClose>
          <Button onClick={submit} variant="destructive" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Remove
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
