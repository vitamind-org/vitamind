import type { ComponentType } from 'react';
import ConfirmationDialog from './confirmation-dialog';

export type DialogControlProps = { open: boolean; onOpenChange: (open: boolean) => void };

/**
 * Registry of all app-level dialogs that can be opened via `useDialog()`.
 * To register a new dialog: add one entry here mapping key to component.
 */
export const dialogs = {
  confirm: ConfirmationDialog,
} as const satisfies Record<string, ComponentType<any>>;

export type DialogRegistry = typeof dialogs;

export type ConsumerProps<C> = C extends ComponentType<infer P> ? Omit<P, keyof DialogControlProps> : never;
