import { FormEvent, ReactNode, useState } from 'react';
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
import { Button } from '@vitamind/ui/button';
import { useForm } from '@inertiajs/react';
import { Label } from '@vitamind/ui/label';
import { Input } from '@vitamind/ui/input';
import InputError from '@vitamind/ui/input-error';
import { LoaderCircleIcon } from 'lucide-react';
import { Workspace } from '@/types/workspace';
import { Form, FormField, FormFields } from '@vitamind/ui/form';

export default function DeleteWorkspace({ workspace, children }: { workspace: Workspace; children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const form = useForm({
    name: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.delete(`/settings/workspaces/${workspace.id}`, {
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
          <DialogTitle>Delete {workspace.name}</DialogTitle>
          <DialogDescription className="sr-only">Delete workspace and all its resources.</DialogDescription>
        </DialogHeader>

        <Form id="delete-workspace-form" onSubmit={submit} className="p-4">
          <p>Are you sure you want to delete this workspace? This action cannot be undone.</p>
          <FormFields>
            <FormField>
              <Label htmlFor="workspace-name">Name</Label>
              <Input id="workspace-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <InputError message={form.errors.name} />
            </FormField>
          </FormFields>
        </Form>

        <DialogFooter className="gap-2">
          <DialogClose asChild>
            <Button variant="outline">Cancel</Button>
          </DialogClose>

          <Button form="delete-workspace-form" variant="destructive" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="size-4 animate-spin" />}
            Delete Workspace
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
