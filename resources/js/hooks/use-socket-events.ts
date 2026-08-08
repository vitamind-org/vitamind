import { type SocketStatus, useSocketStore } from '@/stores/socket-store';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback, useEffect } from 'react';

export type { SocketStatus } from '@/stores/socket-store';

/**
 * Connects/disconnects the shared Echo connection to match auth + the
 * `features.websocket` flag, and exposes connection status for UI (e.g.
 * `AppHeader`'s indicator). For subscribing to a specific channel's events,
 * use `useBroadcastChannel` instead — this hook only manages the underlying
 * connection.
 */
export function useSocketEvents(): { status: SocketStatus; reconnect: () => void } {
  const { auth, features } = usePage<SharedData>().props;

  const status = useSocketStore((s) => s.status);
  const connect = useSocketStore((s) => s.connect);
  const disconnect = useSocketStore((s) => s.disconnect);
  const storeReconnect = useSocketStore((s) => s.reconnect);

  const websocketEnabled = !!(features as Record<string, boolean> | undefined)?.websocket;

  useEffect(() => {
    if (auth && websocketEnabled) {
      connect();
    } else {
      disconnect();
    }
  }, [auth, websocketEnabled, connect, disconnect]);

  const reconnect = useCallback(() => {
    storeReconnect();
  }, [storeReconnect]);

  return { status, reconnect };
}
