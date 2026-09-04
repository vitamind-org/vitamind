import DateTime from '@/components/date-time';
import { Badge } from '@vitamind/ui/badge';
import { Button } from '@vitamind/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@vitamind/ui/dropdown-menu';
import DeleteWorkspace from './delete-workspace';
import Users from './users';
import WorkspaceForm from './workspace-form';
import { SharedData } from '@/types';
import type { Workspace } from '@/types/workspace';
import { usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { MoreVerticalIcon } from 'lucide-react';
import LeaveWorkspace from './leave-workspace';

const CurrentWorkspace = ({ workspace }: { workspace: Workspace }) => {
  const page = usePage<SharedData>();
  return <>{workspace.id === page.props.auth.currentWorkspace?.id && <Badge variant="default">current</Badge>}</>;
};

export const columns: ColumnDef<Workspace>[] = [
  {
    accessorKey: 'name',
    header: 'Name',
    enableColumnFilter: true,
    enableSorting: true,
    cell: ({ row }) => {
      return (
        <div className="flex items-center space-x-1">
          <span>{row.original.name}</span> <CurrentWorkspace workspace={row.original} />
        </div>
      );
    },
  },
  {
    accessorKey: 'role',
    header: 'Role',
    enableColumnFilter: true,
    enableSorting: true,
    cell: ({ row }) => {
      return <Badge variant="outline">{row.original.role}</Badge>;
    },
  },
  {
    accessorKey: 'created_at',
    header: 'Created at',
    enableColumnFilter: true,
    enableSorting: true,
    cell: ({ row }) => {
      return <DateTime date={row.original.created_at} />;
    },
  },
  {
    id: 'actions',
    enableColumnFilter: false,
    enableSorting: false,
    cell: ({ row }) => {
      return (
        <div className="flex items-center justify-end">
          <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="h-8 w-8 p-0">
                <span className="sr-only">Open menu</span>
                <MoreVerticalIcon />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <Users workspace={row.original}>
                <DropdownMenuItem onSelect={(e) => e.preventDefault()}>Users</DropdownMenuItem>
              </Users>
              <WorkspaceForm workspace={row.original}>
                <DropdownMenuItem onSelect={(e) => e.preventDefault()}>Edit</DropdownMenuItem>
              </WorkspaceForm>
              {row.original.role !== 'owner' && (
                <LeaveWorkspace workspace={row.original}>
                  <DropdownMenuItem onSelect={(e) => e.preventDefault()}>Leave workspace</DropdownMenuItem>
                </LeaveWorkspace>
              )}
              {row.original.role === 'owner' && (
                <>
                  <DropdownMenuSeparator />
                  <DeleteWorkspace workspace={row.original}>
                    <DropdownMenuItem onSelect={(e) => e.preventDefault()} variant="destructive">
                      Delete Workspace
                    </DropdownMenuItem>
                  </DeleteWorkspace>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      );
    },
  },
];
