import AuthLayout from '@/layouts/auth/layout';
import { Card, CardContent, CardHeader, CardRow, CardTitle } from '@/components/ui/card';
import IconBanner from '@/components/icon-banner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { WorkspaceUser } from '@/types/workspace-user';
import { PageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { InfoIcon, LoaderCircleIcon } from 'lucide-react';
import { FormEvent } from 'react';

export default function WorkspaceOnboarding() {
  const page = usePage<
    PageProps<{
      invitations: WorkspaceUser[];
      suggestedWorkspaceName: string;
      emailMismatch: { email: string; workspace_name: string } | null;
    }>
  >();
  const invitations = page.props.invitations;
  const emailMismatch = page.props.emailMismatch;

  const createForm = useForm({
    name: page.props.suggestedWorkspaceName,
  });

  const submitCreate = (e: FormEvent) => {
    e.preventDefault();
    createForm.post('/settings/workspaces');
  };

  return (
    <AuthLayout title="Choose your workspace" description="Accept an invitation or create your own workspace to get started.">
      <Head title="Choose your workspace" />

      <div className="flex w-full flex-col gap-6">
        {emailMismatch && (
          <IconBanner
            icon={InfoIcon}
            iconColor="#fdf6e3"
            circleClassName="bg-warning"
            tintClassName="border-warning/30 bg-warning/10"
            title="You signed up with a different email"
            description={
              <>
                The invitation to <span className="text-foreground font-medium">{emailMismatch.workspace_name}</span> was sent to{' '}
                <span className="text-foreground font-medium">{emailMismatch.email}</span>. Open that email and use its link to join.
              </>
            }
          />
        )}

        {invitations.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Pending invitations</CardTitle>
            </CardHeader>
            <CardContent className="divide-y p-0">
              {invitations.map((invitation) => (
                <InvitationRow key={invitation.id} invitation={invitation} />
              ))}
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle>Create your own workspace</CardTitle>
          </CardHeader>
          <CardContent className="p-4">
            <Form id="onboarding-create-workspace-form" onSubmit={submitCreate}>
              <FormFields>
                <FormField>
                  <Label htmlFor="name">Workspace name</Label>
                  <Input
                    id="name"
                    type="text"
                    value={createForm.data.name}
                    onChange={(e) => createForm.setData('name', e.target.value)}
                  />
                  <InputError message={createForm.errors.name} />
                </FormField>
              </FormFields>
              <Button type="submit" className="w-full" disabled={createForm.processing}>
                {createForm.processing && <LoaderCircleIcon className="animate-spin" />}
                Create workspace
              </Button>
            </Form>
          </CardContent>
        </Card>
      </div>
    </AuthLayout>
  );
}

function InvitationRow({ invitation }: { invitation: WorkspaceUser }) {
  const form = useForm();

  const accept = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/settings/workspaces/onboarding/${invitation.id}/accept`);
  };

  return (
    <CardRow>
      <div>
        <p className="font-medium">{invitation.workspace_name}</p>
        <p className="text-muted-foreground text-sm">{invitation.role}</p>
      </div>
      <Button onClick={accept} disabled={form.processing}>
        {form.processing && <LoaderCircleIcon className="animate-spin" />}
        Accept
      </Button>
    </CardRow>
  );
}
