import { type SocketStatus, useSocketStore } from '../stores/socket-store';
import { useFeature } from '@/hooks/use-feature';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useCallback, useEffect } from 'react';

export type { SocketStatus } from '../stores/socket-store';

/**
 * Connects/disconnects the shared Echo connection to match auth + the
 * `features.websocket` flag, and exposes connection status for UI (e.g.
 * `AppHeader`'s indicator). For subscribing to a specific channel's events,
 * use `useBroadcastChannel` instead — this hook only manages the underlying
 * connection.
 */
export function useSocketEvents(): { status: SocketStatus; reconnect: () => void } {
  const { auth } = usePage<SharedData>().props;

  const status = useSocketStore((s) => s.status);
  const connect = useSocketStore((s) => s.connect);
  const disconnect = useSocketStore((s) => s.disconnect);
  const storeReconnect = useSocketStore((s) => s.reconnect);

  const websocketEnabled = useFeature('websocket');
  const isAuthenticated = !!auth?.user;

  useEffect(() => {
    if (isAuthenticated && websocketEnabled) {
      connect();
    } else {
      disconnect();
    }
  }, [isAuthenticated, websocketEnabled, connect, disconnect]);

  const reconnect = useCallback(() => {
    storeReconnect();
  }, [storeReconnect]);

  return { status, reconnect };
}
