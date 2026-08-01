import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { useBootstrapStore } from './stores/bootstrap-store';

const appName = import.meta.env.VITE_APP_NAME || 'VitaminD';

useBootstrapStore.getState().hydrateFromCache();

createInertiaApp({
  resolve: (name) => {
    if (name.startsWith('@plugin/')) {
      const pages = import.meta.glob('../../app/Plugins/Local/**/resources/js/pages/**/*.tsx');
      const nameParts = name.split('/');
      const pluginKebabName = nameParts[1];
      const pageSubPath = nameParts.slice(2).join('/');

      const match = Object.keys(pages).find((path) => {
        const normalizedPath = path.toLowerCase().replace(/\\/g, '/');
        const parts = normalizedPath.split('/');
        const localIndex = parts.indexOf('local');
        if (localIndex === -1 || localIndex + 2 >= parts.length) return false;

        const pluginFolder = parts[localIndex + 2];
        const cleanPluginKebab = pluginKebabName.replace(/-/g, '');

        return pluginFolder === cleanPluginKebab && normalizedPath.endsWith(`${pageSubPath.toLowerCase()}.tsx`);
      });

      if (!match) {
        throw new Error(`Page not found: ${name}`);
      }

      const page = pages[match];
      return typeof page === 'function' ? (page() as any) : page;
    } else {
      const pages = import.meta.glob('./pages/**/*.tsx');
      const match = pages[`./pages/${name}.tsx`];
      if (!match) {
        throw new Error(`Page not found: ${name}`);
      }
      return typeof match === 'function' ? (match() as any) : match;
    }
  },
  title: (title) => `${title} - ${appName}`,
  setup({ el, App, props }) {
    if (el) {
      const root = createRoot(el);
      root.render(<App {...props} />);
    }
  },
  progress: {
    color: '#5a5bc5',
  },
});

initializeTheme();
