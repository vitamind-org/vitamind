import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { CheckIcon, FolderPenIcon, XIcon } from 'lucide-react';
import VisibilitySelect, { Visibility } from './visibility-select';

/**
 * Rendered only while the "New folder" header button has it open — laid
 * out to match a persisted folder/file row exactly (icon, name, spacer,
 * visibility dropdown, icon buttons) so it reads as "the next row is being
 * created" rather than a separate form. FolderPenIcon (vs. persisted
 * folders' plain FolderIcon) is what marks this row as the in-progress
 * form, not a real folder.
 */
export default function CreateFolderForm({
  parentId,
  defaultVisibility,
  onClose,
}: {
  parentId: string | null;
  defaultVisibility: Visibility;
  onClose: () => void;
}) {
  const form = useForm({
    name: '',
    parent_id: parentId,
    visibility: defaultVisibility,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();

    form.post('/archive/folders', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        onClose();
      },
    });
  };

  const cancel = () => {
    form.reset();
    form.clearErrors();
    onClose();
  };

  return (
    <li>
      <form onSubmit={submit} className="flex items-center gap-4 p-3">
        <FolderPenIcon className="size-4 shrink-0 text-neutral-500" />
        <div className="min-w-0 flex-1">
          <Input
            autoFocus
            placeholder="Folder name"
            value={form.data.name}
            onChange={(e) => form.setData('name', e.target.value)}
            className="h-8"
          />
          <InputError message={form.errors.name} />
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <VisibilitySelect
            value={form.data.visibility as Visibility}
            onChange={(visibility) => form.setData('visibility', visibility)}
            parentVisibility={defaultVisibility}
            iconOnly
          />
          <Button type="submit" variant="ghost" size="icon" disabled={form.processing}>
            <CheckIcon className="size-4" />
          </Button>
          <Button type="button" variant="ghost" size="icon" onClick={cancel}>
            <XIcon className="size-4" />
          </Button>
        </div>
      </form>
    </li>
  );
}
