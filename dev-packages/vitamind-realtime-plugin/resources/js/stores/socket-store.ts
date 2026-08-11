import { disconnectEcho, getEcho } from '../lib/echo';
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

type EchoInstance = NonNullable<ReturnType<typeof getEcho>>;
type PusherConnection = EchoInstance['connector']['pusher']['connection'];

// Tracked outside the store (not reactive state) so repeated connect()
// calls — e.g. from use-socket-events.ts's effect re-running on every
// Inertia navigation — can detect they're already bound to the current
// connection instead of stacking a new `state_change` handler each time.
let boundConnection: PusherConnection | null = null;
let boundHandler: ((event: { current: string }) => void) | null = null;

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

    if (boundConnection === connection) return;

    const handler = ({ current }: { current: string }) => {
      set({ status: mapPusherState(current) });
    };

    connection.bind('state_change', handler);
    boundConnection = connection;
    boundHandler = handler;
  },

  disconnect: () => {
    if (boundConnection && boundHandler) {
      boundConnection.unbind('state_change', boundHandler);
    }
    boundConnection = null;
    boundHandler = null;

    disconnectEcho();
    set({ status: 'disconnected' });
  },

  reconnect: () => {
    useSocketStore.getState().disconnect();
    useSocketStore.getState().connect();
  },
}));
