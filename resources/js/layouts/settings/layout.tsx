import { type NavItem, SharedData } from '@/types';
import { UserIcon, ListIcon, KeyIcon } from 'lucide-react';
import { ReactNode, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import Layout from '@/layouts/app/layout';

export default function SettingsLayout({ children }: { children: ReactNode }) {
  const { props } = usePage<SharedData>();

  // Determine if the projects feature is enabled from backend shared data
  const isProjectsEnabled = useMemo(() => {
    const features = props.features as Record<string, boolean> | undefined;
    return !!(features && features.projects);
  }, [props.features]);

  const sidebarNavItems = useMemo(() => {
    const items: NavItem[] = [
      {
        title: 'Profile',
        href: route('profile'),
        icon: UserIcon,
      },
    ];

    if (isProjectsEnabled) {
      items.push({
        title: 'Projects',
        href: route('projects'),
        icon: ListIcon,
      });
    }

    items.push({
      title: 'API Keys',
      href: route('api-keys'),
      icon: KeyIcon,
    });

    return items;
  }, [isProjectsEnabled]);

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
