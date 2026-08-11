import { PageProps as InertiaPageProps } from '@inertiajs/core';
import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import { SharedData } from './';

declare global {
  interface Window {
    axios: AxiosInstance;
    // Placeholder seam for a future runtime-registry plugin delivery mode
    // (self-contained bundles that self-register here instead of being
    // compiled from source alongside the host). Not populated by anything
    // yet — see design.md (D3) in
    // openspec/changes/fix-plugin-frontend-distribution.
    __VITAMIND_PLUGIN_PAGES__?: Record<string, unknown>;
  }
  const route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
  interface PageProps extends InertiaPageProps, SharedData {}
}
