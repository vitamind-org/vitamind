import { type NavItem, SharedData } from '@/types';
import { UsersIcon, PlugIcon } from 'lucide-react';
import * as Icons from 'lucide-react';
import { ReactNode, useMemo } from 'react';
import Layout from '@/layouts/app/layout';
import VitaminIcon from '@/icons/vitamin';
import { usePage } from '@inertiajs/react';

export default function AdminLayout({ children }: { children: ReactNode }) {
  const { props } = usePage<SharedData>();
  const pluginPages = props.pluginPages || [];

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

    // Dynamic plugin pages
    const getIconComponent = (iconName: string) => {
      if (!iconName) return Icons.PackageIcon;
      const pascalName = iconName
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join('');
      return (Icons as any)[pascalName] || (Icons as any)[`${pascalName}Icon`] || Icons.PackageIcon;
    };

    if (hasRoute('plugins.page')) {
      pluginPages.forEach((p) => {
        if (p.admin_only) {
          items.push({
            title: p.title,
            // @ts-ignore
            href: route('plugins.page', p.key),
            icon: getIconComponent(p.icon),
          });
        }
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
  }, [pluginPages]);

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
