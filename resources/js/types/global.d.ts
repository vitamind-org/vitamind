import { PageProps as InertiaPageProps } from '@inertiajs/core';
import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import { SharedData } from './';

declare global {
  interface Window {
    axios: AxiosInstance;
  }
  const route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
  interface PageProps extends InertiaPageProps, SharedData {}
}
