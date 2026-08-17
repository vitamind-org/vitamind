// Host's own contract for what it expects from vitamind/realtime-plugin,
// consumed via usePlugin('realtime-plugin') — deliberately not imported
// from the plugin's own source (@plugin/realtime-plugin/...). Named type
// imports through that wildcard don't type-check (TS2709: "Cannot use
// namespace as a type" — an empty `declare module '@plugin/*';` ambient
// module can't carry precise named types, only opaque/any-shaped values).
// Keeping the contract here also matches design.md (D3): the host defines
// the shape it depends on, independent of the plugin's internal types.
import type { QueryKey } from '@tanstack/react-query';

export type SocketStatus = 'connecting' | 'connected' | 'disconnected';

// Mirrors the plugin's own BroadcastEventTarget (query-key array to
// invalidate, or a plain callback) so a wrongly-typed event target — e.g. a
// number or plain object — can't slip through and be called as a function.
export type BroadcastEventTarget = QueryKey | ((payload: unknown) => void);

export type RealtimePlugin = {
  useSocketEvents?: () => { status: SocketStatus; reconnect: () => void };
  useBroadcastChannel?: (
    channelName: string | null | undefined,
    events: Record<string, BroadcastEventTarget>,
    options?: { private?: boolean },
  ) => void;
};
