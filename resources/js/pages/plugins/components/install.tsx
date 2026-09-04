import { DropdownMenuItem } from '@vitamind/ui/dropdown-menu';
import { Plugin } from '@/types/plugin';
import { useDialog } from '@/hooks/use-dialog';

export default function Install({ plugin }: { plugin: Plugin }) {
  const dialog = useDialog();

  return (
    <DropdownMenuItem
      onSelect={() =>
        dialog.confirm.open({
          title: 'Install plugin',
          description: `Are you sure you want to install the plugin ${plugin.name ?? plugin.folder}?`,
          confirmLabel: 'Install',
          method: 'patch',
          url: route('plugins.install'),
          data: { id: plugin.id },
        })
      }
    >
      Install
    </DropdownMenuItem>
  );
}
