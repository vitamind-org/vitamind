type PluginModuleExports = Record<string, unknown>;

// Eagerly loaded (not lazy) because these are hooks/utilities consumed
// synchronously during render — unlike Inertia pages (app.tsx's resolve()),
// which stay lazy/code-split on purpose. Page files are excluded here since
// the page resolver already owns them separately. Each plugin's named
// exports are merged into one flat object per plugin, so two files within
// the same plugin should not export the same name.
const modules = import.meta.glob(
  [
    '../../../vendor/vitamind/*/resources/js/**/*.{ts,tsx}',
    '!../../../vendor/vitamind/*/resources/js/pages/**',
  ],
  { eager: true },
) as Record<string, PluginModuleExports>;

const byPlugin: Record<string, PluginModuleExports> = {};

for (const path of Object.keys(modules)) {
  const normalized = path.toLowerCase().replace(/\\/g, '/');
  const parts = normalized.split('/');
  const vitamindIndex = parts.indexOf('vitamind');
  if (vitamindIndex === -1 || vitamindIndex + 1 >= parts.length) continue;

  const plugin = parts[vitamindIndex + 1];
  byPlugin[plugin] = { ...byPlugin[plugin], ...modules[path] };
}

/**
 * Stable calling interface for a distributed plugin's non-page frontend
 * exports (hooks, utilities). Returns an empty object — never throws — when
 * the plugin isn't installed under vendor/vitamind/, so host code (e.g.
 * layout.tsx) can destructure defensively instead of crashing on forks that
 * don't have every plugin.
 *
 * Resolution today reads from a build-time glob (host and plugin compiled
 * together). If a plugin later ships as a self-contained runtime-registered
 * bundle instead, only this function's implementation needs to change —
 * call sites stay the same. See design.md (D3) in
 * openspec/changes/fix-plugin-frontend-distribution.
 */
export function usePlugin(name: string): PluginModuleExports {
  return byPlugin[name] ?? {};
}
