import Container from '@/components/container';
import { DataTable } from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import SettingsLayout from '@/layouts/settings/layout';
import { columns as workspaceColumns } from '@/pages/workspaces/components/columns';
import { columns as invitationColumns } from '@/pages/workspaces/components/invitations';
import WorkspaceForm from '@/pages/workspaces/components/workspace-form';
import { PageProps, PaginatedData } from '@/types';
import { Workspace } from '@/types/workspace';
import { Head, usePage } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { WorkspaceUser } from '@/types/workspace-user';

export default function Workspaces() {
  const page = usePage<PageProps<{
    workspaces: PaginatedData<Workspace>;
    invitations: PaginatedData<WorkspaceUser>;
  }>>();

  return (
    <SettingsLayout>
      <Head title="Workspaces" />

      <Container className="max-w-5xl">
        <div className="flex items-start justify-between">
          <Heading title="Workspaces" description="Here you can manage your workspaces" />
          <div className="flex items-center gap-2">
            <WorkspaceForm>
              <Button>
                <PlusIcon />
                Create workspace
              </Button>
            </WorkspaceForm>
          </div>
        </div>
        <DataTable columns={workspaceColumns} paginatedData={page.props.workspaces} />

        {page.props.invitations && page.props.invitations.data && page.props.invitations.data.length > 0 && (
          <div className="mt-8 space-y-4">
            <Heading title="Invitations" description="Here you can see the workspaces you're invited to" />
            <DataTable columns={invitationColumns} paginatedData={page.props.invitations} />
          </div>
        )}
      </Container>
    </SettingsLayout>
  );
}
