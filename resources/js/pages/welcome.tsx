import { Head, Link } from '@inertiajs/react';
import VitaminIcon from '@/icons/vitamin';

export default function Welcome({
  canLogin,
  canRegister,
}: {
  canLogin: boolean;
  canRegister: boolean;
}) {
  return (
    <>
      <Head title="Welcome to VitaminD" />
      <div className="bg-background flex min-h-screen flex-col items-center justify-center p-6 text-foreground">
        <div className="flex flex-col items-center gap-6 max-w-md text-center">
          <div className="flex h-16 w-16 items-center justify-center rounded-xl bg-neutral-100 p-2 dark:bg-neutral-800">
            <VitaminIcon className="h-12 w-12 text-primary" />
          </div>
          <h1 className="text-4xl font-extrabold tracking-tight lg:text-5xl">
            VitaminD
          </h1>
          <p className="text-muted-foreground text-lg">
            A beautiful, modular, and developer-friendly control panel starter kit powered by Laravel, React, Inertia, and Tailwind CSS.
          </p>
          <div className="flex flex-wrap gap-4 justify-center">
            {canLogin && (
              <Link
                href="/login"
                className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90"
              >
                Log in
              </Link>
            )}
            {canRegister && (
              <Link
                href="/register"
                className="inline-flex h-10 items-center justify-center rounded-md border border-input bg-background px-6 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
              >
                Register
              </Link>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
