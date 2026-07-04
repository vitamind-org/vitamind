import { type SharedData } from '@/types';
import { type Workspace } from '@/types/workspace';
import { useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { ChevronsUpDownIcon, PlusIcon } from 'lucide-react';
import { useInitials } from '@/hooks/use-initials';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import WorkspaceForm from '@/pages/workspaces/components/workspace-form';
import { WorkspaceSelect } from '@/components/workspace-select';
import { CommandGroup, CommandItem } from '@/components/ui/command';

export function WorkspaceSwitch() {
  const page = usePage<SharedData>();
  const { auth } = page.props;
  const [open, setOpen] = useState(false);
  const [workspaceFormOpen, setWorkspaceFormOpen] = useState(false);
  const [selected, setSelected] = useState<string>(auth.currentWorkspace?.id?.toString() ?? '');
  const initials = useInitials();
  const form = useForm();

  useEffect(() => {
    setSelected(auth.currentWorkspace?.id?.toString() ?? '');
  }, [auth.currentWorkspace?.id]);

  const handleWorkspaceChange = (value: string, workspace: Workspace) => {
    setSelected(value);
    setOpen(false);
    form.patch(route('workspaces.switch', { workspace: workspace.id, currentPath: window.location.pathname }));
  };

  const footer = (
    <CommandGroup>
      <WorkspaceForm defaultOpen={workspaceFormOpen} onOpenChange={setWorkspaceFormOpen}>
        <CommandItem
          value="create-workspace"
          onSelect={() => {
            setWorkspaceFormOpen(true);
          }}
          className="gap-0"
        >
          <div className="flex items-center">
            <PlusIcon size={5} />
            <span className="ml-2">Create new workspace</span>
          </div>
        </CommandItem>
      </WorkspaceForm>
    </CommandGroup>
  );

  const trigger = (
    <Button variant="ghost" className="px-1!">
      <Avatar className="size-6 rounded-sm">
        <AvatarFallback className="rounded-sm">{initials(auth.currentWorkspace?.name ?? '')}</AvatarFallback>
      </Avatar>
      <span className="hidden lg:flex">{auth.currentWorkspace?.name}</span>
      <ChevronsUpDownIcon size={5} />
    </Button>
  );

  return (
    <div className="flex items-center">
      <WorkspaceSelect value={selected} onValueChange={handleWorkspaceChange} trigger={trigger} open={open} onOpenChange={setOpen} footer={footer} />
    </div>
  );
}
