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
      // Distributed plugins (Composer/GitHub) live under vendor/vitamind/*
      // (symlinked to dev-packages/vitamind-* in this monorepo), compiled
      // together with the host in this same Vite build. See design.md in
      // openspec/changes/fix-plugin-frontend-distribution for why this is a
      // build-time glob rather than a runtime registry for now.
      const pages = import.meta.glob('../../vendor/vitamind/*/resources/js/pages/**/*.tsx');
      const nameParts = name.split('/');
      const pluginKebabName = nameParts[1];
      const pageSubPath = nameParts.slice(2).join('/');

      const match = Object.keys(pages).find((path) => {
        const normalizedPath = path.toLowerCase().replace(/\\/g, '/');
        const parts = normalizedPath.split('/');
        const vitamindIndex = parts.indexOf('vitamind');
        if (vitamindIndex === -1 || vitamindIndex + 1 >= parts.length) return false;

        const pluginFolder = parts[vitamindIndex + 1];

        return pluginFolder === pluginKebabName && normalizedPath.endsWith(`${pageSubPath.toLowerCase()}.tsx`);
      });

      if (!match) {
        // Fallback for a future delivery mode where a plugin ships a
        // self-contained bundle (injected via <script>, self-registering
        // into this global) instead of raw source compiled alongside the
        // host — not implemented yet, see design.md Non-Goals in
        // openspec/changes/fix-plugin-frontend-distribution. Kept as an
        // empty seam now so call sites never need to change later.
        const registered = window.__VITAMIND_PLUGIN_PAGES__?.[name];
        if (registered) {
          return registered;
        }

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
