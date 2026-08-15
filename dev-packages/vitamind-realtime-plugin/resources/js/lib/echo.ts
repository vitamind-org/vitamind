import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: Echo<'reverb'>;
  }
}

let echo: Echo<'reverb'> | null = null;

/**
 * Lazily creates (once) and returns the shared Echo/Reverb connection.
 * Returns null when VITE_REVERB_APP_KEY isn't set — the safe default for a
 * fresh install where `vitamin-d.features.websocket` is off and nothing
 * populated the Reverb env vars, per design.md's D1/D6. Callers gate on the
 * `features.websocket` Inertia prop before calling this; the key check here
 * is a second, independent safety net.
 */
export function getEcho(): Echo<'reverb'> | null {
  if (echo) return echo;

  const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
  if (!key) return null;

  window.Pusher = Pusher;

  echo = new Echo<'reverb'>({
    broadcaster: 'reverb',
    key,
    // Falls back to the server's own default (dev-packages/vitamind-realtime-plugin/config/broadcasting.php)
    // rather than a bare `as string` cast, which would silently hand pusher-js
    // `undefined` and make it fall back to its own SaaS cluster host instead.
    wsHost: (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? '127.0.0.1',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME as string | undefined) === 'https',
    enabledTransports: ['ws', 'wss'],
  });

  window.Echo = echo;

  return echo;
}

/**
 * Tears down the shared connection (e.g. on logout) so a subsequent
 * `getEcho()` call opens a fresh one rather than reusing a disconnected
 * instance.
 */
export function disconnectEcho(): void {
  echo?.disconnect();
  echo = null;
  delete (window as Partial<Window>).Echo;
}
