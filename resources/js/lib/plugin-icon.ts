import * as Icons from 'lucide-react';

/**
 * Resolves a plugin-registered `RegisterPage`/`RegisterPageGroup` kebab-case
 * icon name (e.g. `check-square`) to its `lucide-react` component (e.g.
 * `CheckSquareIcon`), falling back to a generic package icon when unmatched.
 */
export function getPluginIconComponent(iconName: string) {
  if (!iconName) return Icons.PackageIcon;

  const pascalName = iconName
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join('');

  return (Icons as any)[pascalName] || (Icons as any)[`${pascalName}Icon`] || Icons.PackageIcon;
}
