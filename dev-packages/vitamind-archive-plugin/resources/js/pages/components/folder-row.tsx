import { FormEvent, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import { Button } from '@vitamind/ui/button';
import { Input } from '@vitamind/ui/input';
import InputError from '@vitamind/ui/input-error';
import { CheckIcon, FolderIcon, SquarePenIcon, Trash2Icon, XIcon } from 'lucide-react';
import VisibilitySelect, { Visibility } from './visibility-select';

interface FolderSummary {
  uuid: string;
  name: string;
  visibility: Visibility;
}

/**
 * Renders as a persisted-item row, or — while renaming — swaps its label
 * (icon + link) for an inline name input in the same slot, keeping the
 * visibility dropdown in place and swapping the trailing icon buttons for
 * confirm/cancel. Mirrors CreateFolderForm's row layout so a row being
 * edited reads the same way as a row being created.
 */
export default function FolderRow({ folder, parentVisibility }: { folder: FolderSummary; parentVisibility: Visibility }) {
  const [renaming, setRenaming] = useState(false);
  const form = useForm({ name: folder.name });

  const updateVisibility = (visibility: Visibility) => {
    router.patch(`/archive/folders/${folder.uuid}`, { visibility }, { preserveScroll: true });
  };

  const remove = () => {
    router.delete(`/archive/folders/${folder.uuid}`, {
      preserveScroll: true,
      onError: (errors) => {
        if (errors.folder) {
          toast.error(errors.folder);
        }
      },
    });
  };

  const submitRename = (e: FormEvent) => {
    e.preventDefault();

    form.patch(`/archive/folders/${folder.uuid}`, {
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
          <FolderIcon className="size-4 shrink-0" />
          <div className="min-w-0 flex-1">
            <Input autoFocus value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="h-8" />
            <InputError message={form.errors.name} />
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <VisibilitySelect value={folder.visibility} onChange={updateVisibility} parentVisibility={parentVisibility} iconOnly />
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
      <Link href={`/archive/folders/${folder.uuid}`} className="flex min-w-0 flex-1 items-center gap-2">
        <FolderIcon className="size-4 shrink-0" />
        <span className="truncate">{folder.name}</span>
      </Link>
      <div className="flex shrink-0 items-center gap-2">
        <VisibilitySelect value={folder.visibility} onChange={updateVisibility} parentVisibility={parentVisibility} iconOnly />
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
