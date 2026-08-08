import { disconnectEcho, getEcho } from '@/lib/echo';
import { create } from 'zustand';

export type SocketStatus = 'connecting' | 'connected' | 'disconnected';

type SocketStore = {
  status: SocketStatus;
  connect: () => void;
  disconnect: () => void;
  reconnect: () => void;
};

function mapPusherState(pusherState: string): SocketStatus {
  if (pusherState === 'connected') return 'connected';
  if (pusherState === 'connecting' || pusherState === 'unavailable') return 'connecting';
  return 'disconnected';
}

/**
 * Connection status only — legitimately client-only UI state (design.md's
 * D4 keeps *data* out of Zustand, not connection bookkeeping). Sourced from
 * the Echo/Reverb connection's own Pusher-protocol connection state, so
 * reconnection itself is handled by pusher-js internally rather than the
 * hand-rolled backoff loop this store used to implement (design.md's D6).
 */
export const useSocketStore = create<SocketStore>((set) => ({
  status: 'disconnected',

  connect: () => {
    const echo = getEcho();

    if (!echo) {
      set({ status: 'disconnected' });
      return;
    }

    const { connection } = echo.connector.pusher;

    set({ status: mapPusherState(connection.state) });

    connection.bind('state_change', ({ current }: { current: string }) => {
      set({ status: mapPusherState(current) });
    });
  },

  disconnect: () => {
    disconnectEcho();
    set({ status: 'disconnected' });
  },

  reconnect: () => {
    disconnectEcho();
    useSocketStore.getState().connect();
  },
}));
