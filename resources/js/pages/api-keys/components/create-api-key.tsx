import { ClipboardCheckIcon, ClipboardIcon, LoaderCircle } from 'lucide-react';
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
import { useForm } from '@inertiajs/react';
import React, { FormEventHandler, ReactNode, useRef, useState } from 'react';
import { Label } from '@vitamind/ui/label';
import InputError from '@vitamind/ui/input-error';
import { Form, FormField, FormFields } from '@vitamind/ui/form';
import { Input } from '@vitamind/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@vitamind/ui/select';
import { Workspace } from '@/types/workspace';
import { MultiSelect } from '@/components/multi-select';

type ApiKeyForm = {
  name: string;
  permission: string;
  workspaces: string[];
};

export default function CreateApiKey({ children, workspaces = [] }: { children: ReactNode; workspaces?: Workspace[] }) {
  const [open, setOpen] = useState(false);
  const [token, setToken] = useState<string | undefined>();
  const tokenInputRef = useRef<HTMLInputElement>(null);
  const [copySuccess, setCopySuccess] = useState(false);
  const copyToClipboard = () => {
    tokenInputRef.current?.select();
    navigator.clipboard.writeText(token || '').then(() => {
      setCopySuccess(true);
      setTimeout(() => {
        setCopySuccess(false);
      }, 2000);
    });
  };

  const form = useForm<Required<ApiKeyForm>>({
    name: '',
    permission: '',
    workspaces: [],
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route('api-keys.store'), {
      onSuccess: (page) => {
        const flash = page.props.flash as { data?: { token?: string } };
        setToken(flash.data?.token);
      },
    });
  };

  const onOpenChange = (isOpen: boolean) => {
    setOpen(isOpen);
    if (!isOpen) {
      setToken(undefined);
      form.reset();
    }
  };

  const workspaceList: Workspace[] = Array.isArray(workspaces)
    ? workspaces
    : workspaces && typeof workspaces === 'object' && 'data' in workspaces && Array.isArray((workspaces as any).data)
      ? (workspaces as any).data
      : [];

  const workspaceOptions = workspaceList.map((workspace) => ({
    label: workspace.name,
    value: String(workspace.id),
  }));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent className="max-h-screen overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Create an API key</DialogTitle>
          <DialogDescription className="sr-only">Create a new api key</DialogDescription>
        </DialogHeader>
        <Form id="create-tag-form" onSubmit={submit} className="p-4">
          {token ? (
            <FormFields>
              <FormField>
                <Label htmlFor="token" className="flex items-center gap-1">
                  Token {copySuccess ? <ClipboardCheckIcon className="text-success! size-4" /> : <ClipboardIcon className="size-4" />}
                </Label>
                <Input ref={tokenInputRef} id="token" onClick={copyToClipboard} type="text" value={token || ''} className="cursor-pointer" />
              </FormField>
            </FormFields>
          ) : (
            <FormFields>
              <FormField>
                <Label htmlFor="name">Name</Label>
                <Input type="text" id="name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <InputError message={form.errors.name} />
              </FormField>
              <FormField>
                <Label htmlFor="permission">Permission</Label>
                <Select name="permission" value={form.data.permission} onValueChange={(value) => form.setData('permission', value)}>
                  <SelectTrigger id="permission">
                    <SelectValue placeholder="Select a permission" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      <SelectItem key="permission-read" value="read">
                        read
                      </SelectItem>
                      <SelectItem key="permission-write" value="write">
                        read & write
                      </SelectItem>
                    </SelectGroup>
                  </SelectContent>
                </Select>
                <InputError message={form.errors.permission} />
              </FormField>
              <FormField>
                <Label htmlFor="workspaces">Workspaces</Label>
                <MultiSelect
                  options={workspaceOptions}
                  onValueChange={(value) => form.setData('workspaces', value)}
                  defaultValue={form.data.workspaces}
                  placeholder="All workspaces"
                  maxCount={3}
                />
                <p className="text-muted-foreground text-xs">Leave empty for access to all workspaces.</p>
                <InputError message={form.errors.workspaces} />
              </FormField>
            </FormFields>
          )}
        </Form>
        {!token && (
          <DialogFooter>
            <DialogClose asChild>
              <Button type="button" variant="outline">
                Cancel
              </Button>
            </DialogClose>
            <Button type="button" onClick={submit} disabled={form.processing}>
              {form.processing && <LoaderCircle className="animate-spin" />}
              Create
            </Button>
          </DialogFooter>
        )}
      </DialogContent>
    </Dialog>
  );
}
