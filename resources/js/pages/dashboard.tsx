import AppLayout from '@/layouts/app/layout';
import { Head } from '@inertiajs/react';
import Container from '@/components/container';
import Heading from '@/components/heading';

export default function Dashboard() {
  return (
    <AppLayout>
      <Head title="Dashboard" />
      <Container className="max-w-5xl">
        <Heading title="Dashboard" description="Welcome to your dashboard" />
        <div className="mt-6 overflow-hidden rounded-lg border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
          <p className="text-sm text-neutral-600 dark:text-neutral-400">
            You're logged in!
          </p>
        </div>
      </Container>
    </AppLayout>
  );
}
