import tailwindcss from '@tailwindcss/vite';
import inertia from '@inertiajs/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve, join } from 'node:path';
import { readdirSync, statSync } from 'node:fs';
import { defineConfig } from 'vite';

// Helper to scan plugins and generate aliases dynamically
const getPluginAliases = () => {
  const aliases: Record<string, string> = {};
  const pluginsDir = resolve(__dirname, 'app/Plugins/Local');
  try {
    const vendors = readdirSync(pluginsDir);
    for (const vendor of vendors) {
      const vendorPath = join(pluginsDir, vendor);
      if (statSync(vendorPath).isDirectory()) {
        const plugins = readdirSync(vendorPath);
        for (const plugin of plugins) {
          const pluginPath = join(vendorPath, plugin);
          if (statSync(pluginPath).isDirectory()) {
            const jsDir = join(pluginPath, 'resources/js');
            // convert plugin folder Name to kebab-case
            const kebabName = plugin
              .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
              .toLowerCase();
            aliases[`@plugin/${kebabName}`] = jsDir;
          }
        }
      }
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
      '@': resolve(__dirname, 'resources/js'),
      ...getPluginAliases(),
    },
  },
});
