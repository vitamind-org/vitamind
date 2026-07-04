import { FormEvent, ReactNode, useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { LoaderCircleIcon } from 'lucide-react';
import { Workspace } from '@/types/workspace';

export default function LeaveWorkspace({ workspace, children }: { workspace: Workspace; children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const form = useForm();

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.delete(`/settings/workspaces/${workspace.id}/leave`, {
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
          <DialogTitle>Leave {workspace.name}</DialogTitle>
          <DialogDescription className="sr-only">Leave workspace {workspace.name}</DialogDescription>
        </DialogHeader>

        <p className="p-4">Are you sure you want to leave this workspace?</p>

        <DialogFooter className="gap-2">
          <DialogClose asChild>
            <Button variant="outline">Cancel</Button>
          </DialogClose>

          <Button onClick={submit} variant="destructive" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Leave
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
