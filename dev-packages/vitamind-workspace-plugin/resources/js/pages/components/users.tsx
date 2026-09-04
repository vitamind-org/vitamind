import { DataTable } from '@/components/data-table';
import { Badge } from '@vitamind/ui/badge';
import { Button } from '@vitamind/ui/button';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@vitamind/ui/sheet';
import RemoveUser from './remove-user';
import Invite from './invite';
import { Workspace } from '@/types/workspace';
import { WorkspaceUser } from '@/types/workspace-user';
import { ColumnDef } from '@tanstack/react-table';
import { TrashIcon } from 'lucide-react';
import { ReactNode, useState } from 'react';

const columns: ColumnDef<WorkspaceUser>[] = [
  {
    accessorKey: 'email',
    header: 'Email',
    enableColumnFilter: true,
    enableSorting: true,
  },
  {
    id: 'role',
    header: 'Role',
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return <Badge variant="outline">{row.original.role}</Badge>;
    },
  },
  {
    id: 'status',
    header: 'Status',
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return <Badge variant="outline">{row.original.type === 'user' ? 'registered' : 'invited'}</Badge>;
    },
  },
  {
    id: 'actions',
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return (
        <div className="flex items-center justify-end">
          <RemoveUser workspaceId={row.original.workspace_id} user={row.original}>
            <Button variant="outline" size="sm" className="size-7">
              <TrashIcon className="size-3" />
            </Button>
          </RemoveUser>
        </div>
      );
    },
  },
];

export default function Users({ workspace, children }: { workspace: Workspace; children?: ReactNode }) {
  const [open, setOpen] = useState(false);
  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>{children}</SheetTrigger>
      <SheetContent className="sm:max-w-2xl">
        <SheetHeader>
          <SheetTitle>Workspace users</SheetTitle>
          <SheetDescription className="sr-only">Here you can manage workspace users</SheetDescription>
        </SheetHeader>
        <div className="p-4">
          <DataTable columns={columns} data={[...(workspace.owner ? [workspace.owner] : []), ...(workspace.users || [])]} />
        </div>
        <SheetFooter>
          <div className="flex items-center gap-2">
            <SheetClose asChild>
              <Button variant="outline">Close</Button>
            </SheetClose>
            <Invite workspace={workspace}>
              <Button>Invite</Button>
            </Invite>
          </div>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
