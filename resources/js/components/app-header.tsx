import { SidebarTrigger } from '@/components/ui/sidebar';
import { ProjectSwitch } from '@/components/project-switch';
import { HeartIcon, SlashIcon, WifiIcon, WifiOffIcon } from 'lucide-react';
import AppCommand from '@/components/app-command';
import { usePage } from '@inertiajs/react';
import { SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { type SocketStatus } from '@/hooks/use-socket-events';
import { useMemo } from 'react';

export function AppHeader({ socketStatus, socketReconnect }: { socketStatus: SocketStatus; socketReconnect: () => void }) {
  const page = usePage<SharedData>();

  // Safely check if projects feature is enabled
  const isProjectsEnabled = useMemo(() => {
    const features = page.props.features as Record<string, boolean> | undefined;
    return !!(features && features.projects);
  }, [page.props.features]);

  // Safely check if events.token route exists (meaning WS is enabled)
  const isWebSocketEnabled = useMemo(() => {
    // @ts-ignore
    return typeof route !== 'undefined' && typeof route().has === 'function' && route().has('events.token');
  }, []);

  return (
    <header className="bg-background -ml-1 flex h-12 shrink-0 items-center justify-between gap-2 border-b p-4 md:-ml-2">
      <div className="flex items-center">
        <SidebarTrigger className="-ml-1 md:hidden" />
        <div className="flex items-center space-x-2 text-xs">
          {isProjectsEnabled && <ProjectSwitch />}
        </div>
      </div>
      <div className="flex items-center gap-2">
        {isWebSocketEnabled && socketStatus !== 'connected' && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="icon"
                className="size-8"
                onClick={socketReconnect}
                disabled={socketStatus === 'connecting'}
                aria-label={socketStatus === 'connecting' ? 'Connecting to WebSocket' : 'WebSocket disconnected, click to reconnect'}
              >
                {socketStatus === 'connecting' ? <WifiIcon className="size-4 animate-pulse" /> : <WifiOffIcon className="size-4" />}
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              {socketStatus === 'connecting' ? 'Connecting to WebSocket...' : 'WebSocket connection failed. Click to retry.'}
            </TooltipContent>
          </Tooltip>
        )}
        <AppCommand />
      </div>
    </header>
  );
}
