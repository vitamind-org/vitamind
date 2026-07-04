import { type NavItem, SharedData } from '@/types';
import { UserIcon, ListIcon, KeyIcon } from 'lucide-react';
import { ReactNode, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import Layout from '@/layouts/app/layout';

export default function SettingsLayout({ children }: { children: ReactNode }) {
  const { props } = usePage<SharedData>();

  // Determine if the workspaces feature is enabled from backend shared data
  const isWorkspacesEnabled = useMemo(() => {
    const features = props.features as Record<string, boolean> | undefined;
    return !!(features && features.workspaces);
  }, [props.features]);

  const sidebarNavItems = useMemo(() => {
    const items: NavItem[] = [
      {
        title: 'Profile',
        href: route('profile'),
        icon: UserIcon,
      },
    ];

    if (isWorkspacesEnabled) {
      items.push({
        title: 'Workspaces',
        href: route('workspaces'),
        icon: ListIcon,
      });
    }

    items.push({
      title: 'API Keys',
      href: route('api-keys'),
      icon: KeyIcon,
    });

    return items;
  }, [isWorkspacesEnabled]);

  // When server-side rendering, we only render the layout on the client...
  if (typeof window === 'undefined') {
    return null;
  }

  return (
    <Layout secondNavItems={sidebarNavItems} secondNavTitle="Settings">
      {children}
    </Layout>
  );
}
