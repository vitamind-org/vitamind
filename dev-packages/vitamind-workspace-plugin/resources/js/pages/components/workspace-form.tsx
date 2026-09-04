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
import { FormEvent, ReactNode, useEffect, useState } from 'react';
import { Button } from '@vitamind/ui/button';
import { LoaderCircle } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { Label } from '@vitamind/ui/label';
import { Input } from '@vitamind/ui/input';
import InputError from '@vitamind/ui/input-error';
import { Workspace } from '@/types/workspace';
import { Form, FormField, FormFields } from '@vitamind/ui/form';

export default function WorkspaceForm({
  workspace,
  defaultOpen,
  onOpenChange,
  children,
}: {
  workspace?: Workspace;
  defaultOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
  children: ReactNode;
}) {
  const [open, setOpen] = useState(defaultOpen || false);
  useEffect(() => {
    setOpen(defaultOpen || false);
  }, [defaultOpen]);

  const handleOpenChange = (open: boolean) => {
    setOpen(open);
    if (onOpenChange) {
      onOpenChange(open);
    }
  };

  const form = useForm({
    name: workspace?.name || '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();

    if (workspace) {
      form.patch(`/settings/workspaces/${workspace.id}`, {
        onSuccess() {
          handleOpenChange(false);
        },
      });
      return;
    }

    form.post('/settings/workspaces', {
      onSuccess() {
        setOpen(false);
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{workspace ? 'Edit Workspace' : 'Create Workspace'}</DialogTitle>
          <DialogDescription className="sr-only">{workspace ? 'Edit the workspace details.' : 'Here you can create a new workspace.'}</DialogDescription>
        </DialogHeader>
        <Form id="workspace-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="name">Name</Label>
              <Input type="text" id="name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <InputError message={form.errors.name} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline">
              Cancel
            </Button>
          </DialogClose>
          <Button form="workspace-form" type="button" onClick={submit} disabled={form.processing}>
            {form.processing && <LoaderCircle className="animate-spin" />}
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
