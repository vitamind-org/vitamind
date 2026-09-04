import tailwindcss from '@tailwindcss/vite';
import inertia from '@inertiajs/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve, join } from 'node:path';
import { readdirSync, statSync, existsSync } from 'node:fs';
import { defineConfig } from 'vite';

// Scans vendor/vitamind/* (Composer/GitHub "distributed" plugins, symlinked
// to dev-packages/vitamind-* in this monorepo's path-repository setup) and
// generates `@plugin/{kebab-name}` aliases for any plugin that ships a
// resources/js folder. Local plugins (app/Plugins/*) are plain application
// code and intentionally have no alias here — see docs/local-plugins.md.
const getPluginAliases = () => {
  const aliases: Record<string, string> = {};
  const pluginsDir = resolve(__dirname, 'vendor/vitamind');
  try {
    const plugins = readdirSync(pluginsDir);
    for (const plugin of plugins) {
      const pluginPath = join(pluginsDir, plugin);
      if (!statSync(pluginPath).isDirectory()) continue;

      const jsDir = join(pluginPath, 'resources/js');
      if (!existsSync(jsDir)) continue;

      const kebabName = plugin
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .toLowerCase();
      aliases[`@plugin/${kebabName}`] = jsDir;
    }
  } catch (e) {
    // folder might not exist yet
  }
  return aliases;
};

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      refresh: true,
    }),
    inertia({ ssr: false }),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
      // Neutral UI-kit alias: resolves to the same physical location for
      // both host and plugin source, so neither statically depends on the
      // other's `@/` namespace — see openspec/changes/extract-frontend-ui-kit.
      '@vitamind/ui/cn': resolve(__dirname, 'resources/js/lib/utils.ts'),
      '@vitamind/ui': resolve(__dirname, 'resources/js/components/ui'),
      '@': resolve(__dirname, 'resources/js'),
      ...getPluginAliases(),
    },
  },
});
