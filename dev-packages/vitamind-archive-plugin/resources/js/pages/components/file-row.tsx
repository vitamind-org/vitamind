import { FormEvent, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@vitamind/ui/button';
import { Input } from '@vitamind/ui/input';
import InputError from '@vitamind/ui/input-error';
import { CheckIcon, FileIcon, SquarePenIcon, Trash2Icon, XIcon } from 'lucide-react';
import VisibilitySelect, { Visibility } from './visibility-select';

interface FileSummary {
  uuid: string;
  original_name: string;
  extension: string;
  size: number;
  visibility: Visibility;
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/**
 * Mirrors FolderRow's rename layout — inline input in the label slot,
 * confirm/cancel icon buttons in place of the usual edit/delete pair.
 */
export default function FileRow({ file, parentVisibility }: { file: FileSummary; parentVisibility: Visibility }) {
  const [renaming, setRenaming] = useState(false);
  const form = useForm({ original_name: file.original_name });

  const updateVisibility = (visibility: Visibility) => {
    router.patch(`/archive/files/${file.uuid}`, { visibility }, { preserveScroll: true });
  };

  const remove = () => {
    router.delete(`/archive/files/${file.uuid}`, { preserveScroll: true });
  };

  const submitRename = (e: FormEvent) => {
    e.preventDefault();

    form.patch(`/archive/files/${file.uuid}`, {
      preserveScroll: true,
      onSuccess: () => setRenaming(false),
    });
  };

  const cancelRename = () => {
    form.reset();
    form.clearErrors();
    setRenaming(false);
  };

  if (renaming) {
    return (
      <li>
        <form onSubmit={submitRename} className="flex items-center gap-4 p-3">
          <FileIcon className="size-4 shrink-0" />
          <div className="min-w-0 flex-1">
            <Input
              autoFocus
              value={form.data.original_name}
              onChange={(e) => form.setData('original_name', e.target.value)}
              className="h-8"
            />
            <InputError message={form.errors.original_name} />
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <VisibilitySelect value={file.visibility} onChange={updateVisibility} parentVisibility={parentVisibility} iconOnly />
            <Button type="submit" variant="ghost" size="icon" disabled={form.processing}>
              <CheckIcon className="size-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" onClick={cancelRename}>
              <XIcon className="size-4" />
            </Button>
          </div>
        </form>
      </li>
    );
  }

  return (
    <li className="flex items-center justify-between gap-4 p-3">
      <a href={`/archive/f/${file.uuid}`} className="flex min-w-0 flex-1 items-center gap-2">
        <FileIcon className="size-4 shrink-0" />
        <span className="truncate">{file.original_name}</span>
        <span className="shrink-0 text-xs text-neutral-500">{formatSize(file.size)}</span>
      </a>
      <div className="flex shrink-0 items-center gap-2">
        <VisibilitySelect value={file.visibility} onChange={updateVisibility} parentVisibility={parentVisibility} iconOnly />
        <Button variant="ghost" size="icon" onClick={() => setRenaming(true)}>
          <SquarePenIcon className="size-4" />
        </Button>
        <Button variant="ghost" size="icon" onClick={remove}>
          <Trash2Icon className="size-4" />
        </Button>
      </div>
    </li>
  );
}
