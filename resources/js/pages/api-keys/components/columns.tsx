import { ColumnDef } from '@tanstack/react-table';
import DateTime from '@/components/date-time';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@vitamind/ui/dropdown-menu';
import { Button } from '@vitamind/ui/button';
import { MoreVerticalIcon } from 'lucide-react';
import { ApiKey } from '@/types/api-key';
import { Badge } from '@vitamind/ui/badge';
import { Workspace } from '@/types/workspace';
import { useDialog } from '@/hooks/use-dialog';

function Delete({ apiKey }: { apiKey: ApiKey }) {
  const dialog = useDialog();

  return (
    <DropdownMenuItem
      variant="destructive"
      onSelect={() =>
        dialog.confirm.open({
          title: `Delete ${apiKey.name}`,
          description: `Are you sure you want to delete ${apiKey.name}?`,
          variant: 'destructive',
          confirmLabel: 'Delete',
          method: 'delete',
          url: route('api-keys.destroy', apiKey.id),
        })
      }
    >
      Delete
    </DropdownMenuItem>
  );
}

export function getColumns(workspaces: Workspace[] = []): ColumnDef<ApiKey>[] {
  const workspaceList: Workspace[] = Array.isArray(workspaces)
    ? workspaces
    : workspaces && typeof workspaces === 'object' && 'data' in workspaces && Array.isArray((workspaces as any).data)
      ? (workspaces as any).data
      : [];

  return [
    {
      accessorKey: 'name',
      header: 'Name',
      enableColumnFilter: true,
      enableSorting: true,
    },
    {
      accessorKey: 'permissions',
      header: 'Permissions',
      enableColumnFilter: true,
      enableSorting: true,
      cell: ({ row }) => {
        return row.original.permissions.includes('write') ? <span>read & write</span> : <span>read</span>;
      },
    },
    {
      accessorKey: 'workspace_ids',
      header: 'Workspaces',
      enableColumnFilter: false,
      enableSorting: false,
      cell: ({ row }) => {
        const workspaceIds = row.original.workspace_ids;
        if (!workspaceIds || workspaceIds.length === 0) {
          return <Badge variant="outline">All workspaces</Badge>;
        }
        return (
          <div className="flex flex-wrap gap-1">
            {workspaceIds.map((id) => {
              const workspace = workspaceList.find((w) => w.id === id);
              return (
                <Badge key={id} variant="default">
                  {workspace?.name ?? `Workspace #${id}`}
                </Badge>
              );
            })}
          </div>
        );
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
                <Delete apiKey={row.original} />
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        );
      },
    },
  ];
}
