import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { useBootstrapStore } from './stores/bootstrap-store';

const appName = import.meta.env.VITE_APP_NAME || 'VitaminD';

useBootstrapStore.getState().hydrateFromCache();

createInertiaApp({
  pages: './pages',
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
