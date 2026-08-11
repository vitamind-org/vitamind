// Host's own contract for what it expects from vitamind/realtime-plugin,
// consumed via usePlugin('realtime-plugin') — deliberately not imported
// from the plugin's own source (@plugin/realtime-plugin/...). Named type
// imports through that wildcard don't type-check (TS2709: "Cannot use
// namespace as a type" — an empty `declare module '@plugin/*';` ambient
// module can't carry precise named types, only opaque/any-shaped values).
// Keeping the contract here also matches design.md (D3): the host defines
// the shape it depends on, independent of the plugin's internal types.
export type SocketStatus = 'connecting' | 'connected' | 'disconnected';

export type RealtimePlugin = {
  useSocketEvents?: () => { status: SocketStatus; reconnect: () => void };
  useBroadcastChannel?: (
    channelName: string | null | undefined,
    events: Record<string, unknown>,
    options?: { private?: boolean },
  ) => void;
};
