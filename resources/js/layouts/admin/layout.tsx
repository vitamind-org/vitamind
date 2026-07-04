import { type NavItem } from '@/types';
import { UsersIcon, PlugIcon } from 'lucide-react';
import { ReactNode, useMemo } from 'react';
import Layout from '@/layouts/app/layout';
import VitaminIcon from '@/icons/vitamin';

export default function AdminLayout({ children }: { children: ReactNode }) {
  const sidebarNavItems = useMemo(() => {
    const items: NavItem[] = [];

    // Helper to check route existence safely
    const hasRoute = (name: string) => {
      // @ts-ignore
      return typeof route !== 'undefined' && typeof route().has === 'function' && route().has(name);
    };

    if (hasRoute('users')) {
      items.push({
        title: 'Users',
        href: route('users'),
        icon: UsersIcon,
      });
    }

    if (hasRoute('plugins')) {
      items.push({
        title: 'Plugins',
        href: route('plugins'),
        icon: PlugIcon,
      });
    }

    if (hasRoute('settings')) {
      items.push({
        title: 'Settings',
        href: route('settings'),
        icon: VitaminIcon,
      });
    }

    return items;
  }, []);

  // When server-side rendering, we only render the layout on the client...
  if (typeof window === 'undefined') {
    return null;
  }

  return (
    <Layout secondNavItems={sidebarNavItems} secondNavTitle="Admin">
      {children}
    </Layout>
  );
}
