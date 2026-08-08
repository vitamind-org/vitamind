import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export function useFeature(name: string): boolean {
  const page = usePage<SharedData>();
  return !!(page.props.features as Record<string, boolean> | undefined)?.[name];
}
