import SectionLayout from '@/layouts/section/layout';
import { Head } from '@inertiajs/react';
import Container from '@/components/container';
import Heading from '@/components/heading';
import UsersList from '@/pages/users/components/list';
import { Button } from '@/components/ui/button';
import UserForm from '@/pages/users/components/user-form';

export default function Users() {
  return (
    <SectionLayout title="Admin" groupKey="admin">
      <Head title="Users" />

      <Container className="max-w-5xl">
        <div className="flex items-start justify-between">
          <Heading title="Users" description="Here you can manage all users" />
          <UserForm>
            <Button>Create user</Button>
          </UserForm>
        </div>
        <UsersList />
      </Container>
    </SectionLayout>
  );
}
