import { getEcho } from '../lib/echo';
import { type QueryKey, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';

/**
 * A TanStack Query key invalidates that key on the shared cache (design.md's
 * D4 — the common case, for realtime updates to data a REST call already
 * populates). A plain callback is the escape hatch for state that isn't
 * TanStack-Query-owned, e.g. VitaminD core's own `useBootstrapStore`.
 */
export type BroadcastEventTarget = QueryKey | ((payload: unknown) => void);

export type BroadcastEventMap = Record<string, BroadcastEventTarget>;

function isQueryKey(target: BroadcastEventTarget): target is QueryKey {
  return Array.isArray(target);
}

/**
 * Subscribes to an Echo/Reverb channel for the lifetime of the calling
 * component, and applies the mapped target whenever one of `events`'s event
 * names is received — the realtime update flows back through the same
 * cache every REST call already populates (design.md's D4), rather than a
 * parallel store, unless a plain callback target says otherwise.
 *
 * `events` may be a fresh object literal each render; only `channelName`
 * (and the private/public choice) controls when the subscription itself is
 * torn down and re-created — changing which event *names* are being
 * listened for on an already-subscribed channel requires a channel-name
 * change too.
 *
 * No-ops safely when the websocket feature is off (`getEcho()` returns
 * null) or `channelName` is null/undefined, so it's safe to call
 * unconditionally, e.g. with an id that isn't loaded yet.
 */
export function useBroadcastChannel(channelName: string | null | undefined, events: BroadcastEventMap, options?: { private?: boolean }): void {
  const queryClient = useQueryClient();
  const eventsRef = useRef(events);

  useEffect(() => {
    eventsRef.current = events;
  });

  const isPrivate = options?.private ?? true;

  useEffect(() => {
    if (!channelName) return;

    const echo = getEcho();
    if (!echo) return;

    const channel = isPrivate ? echo.private(channelName) : echo.channel(channelName);

    // Bound per event name so cleanup can unbind exactly these listeners
    // (channel.stopListening) rather than echo.leave(channelName), which
    // would unsubscribe the whole channel — including any other hook
    // instance still listening on the same channel name.
    const listeners = Object.keys(eventsRef.current).map((eventName) => {
      const listener = (payload: unknown) => {
        const target = eventsRef.current[eventName];
        if (!target) return;

        if (isQueryKey(target)) {
          queryClient.invalidateQueries({ queryKey: target });
        } else {
          target(payload);
        }
      };

      channel.listen(eventName, listener);

      return { eventName, listener };
    });

    return () => {
      listeners.forEach(({ eventName, listener }) => {
        channel.stopListening(eventName, listener);
      });
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [channelName, isPrivate, queryClient]);
}
