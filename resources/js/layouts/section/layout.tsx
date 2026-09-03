import { type NavItem, SharedData } from '@/types';
import { ReactNode, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import Layout from '@/layouts/app/layout';
import { getPluginIconComponent } from '@/lib/plugin-icon';

/**
 * Renders a main-sidebar entry's second-nav: `coreItems` (native, non-plugin
 * entries the caller already computed — usually empty, since core's own
 * entries are themselves `pluginPages` registrations, see
 * docs/plugin-development/menu-registration.md) merged with every
 * `pluginPages` entry sharing `groupKey`, sorted by `order`.
 *
 * One shared implementation for every group-owning section — the built-in
 * `settings`/`admin` groups and any plugin's own group alike — so none of
 * them need a bespoke layout file reimplementing this filtering/rendering.
 */
export default function SectionLayout({
  title,
  groupKey,
  coreItems = [],
  children,
}: {
  title: string;
  groupKey: string;
  coreItems?: NavItem[];
  children: ReactNode;
}) {
  const { props } = usePage<SharedData>();
  const pluginPages = props.pluginPages || [];

  const sidebarNavItems = useMemo(() => {
    const pluginItems: NavItem[] = pluginPages
      .filter((p) => p.group === groupKey)
      .map((p) => ({
        title: p.title,
        href: p.href,
        icon: getPluginIconComponent(p.icon),
        order: p.order,
      }));

    return [...coreItems, ...pluginItems].sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
  }, [pluginPages, groupKey, coreItems]);

  // When server-side rendering, we only render the layout on the client...
  if (typeof window === 'undefined') {
    return null;
  }

  return (
    <Layout secondNavItems={sidebarNavItems} secondNavTitle={title}>
      {children}
    </Layout>
  );
}
