import { DragEvent, FormEvent, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import InputError from '@/components/ui/input-error';
import { cn } from '@/lib/utils';
import { LoaderCircleIcon, UploadCloudIcon, UploadIcon } from 'lucide-react';
import VisibilitySelect, { Visibility } from './visibility-select';

export default function UploadFileForm({ folderId, defaultVisibility }: { folderId: string | null; defaultVisibility: Visibility }) {
  const [open, setOpen] = useState(false);
  const [dragging, setDragging] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);
  const form = useForm<{ file: File | null; folder_id: string | null; visibility: Visibility }>({
    file: null,
    folder_id: folderId,
    visibility: defaultVisibility,
  });

  const reset = () => {
    form.reset();
    form.clearErrors();
    if (fileInput.current) {
      fileInput.current.value = '';
    }
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();

    if (!form.data.file) {
      return;
    }

    form.post('/archive/files', {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        reset();
        setOpen(false);
      },
    });
  };

  const onDrop = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setDragging(false);

    const dropped = e.dataTransfer.files?.[0];
    if (dropped) {
      form.setData('file', dropped);
    }
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) reset();
      }}
    >
      <DialogTrigger asChild>
        <Button variant="outline">
          <UploadIcon />
          Upload file
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Upload file</DialogTitle>
          <DialogDescription className="sr-only">Upload a file into this folder.</DialogDescription>
        </DialogHeader>

        <form id="upload-file-form" onSubmit={submit} className="space-y-4 p-4">
          <div
            onDragOver={(e) => {
              e.preventDefault();
              setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={onDrop}
            onClick={() => fileInput.current?.click()}
            className={cn(
              'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-8 text-center transition-colors',
              dragging ? 'border-primary bg-primary/5' : 'border-neutral-300 dark:border-neutral-700',
            )}
          >
            <UploadCloudIcon className="size-6 text-neutral-500" />
            {form.data.file ? (
              <p className="text-sm font-medium break-all">{form.data.file.name}</p>
            ) : (
              <p className="text-sm text-neutral-600 dark:text-neutral-400">Drag & drop a file here, or click to browse</p>
            )}
            <input ref={fileInput} type="file" className="hidden" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
          </div>
          <InputError message={form.errors.file} />

          <VisibilitySelect
            value={form.data.visibility}
            onChange={(visibility) => form.setData('visibility', visibility)}
            parentVisibility={defaultVisibility}
          />
        </form>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline">
              Cancel
            </Button>
          </DialogClose>
          <Button form="upload-file-form" type="button" onClick={submit} disabled={form.processing || !form.data.file}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Upload
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
