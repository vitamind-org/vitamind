import { SidebarTrigger } from '@vitamind/ui/sidebar';
import { HeartIcon, SlashIcon, WifiIcon, WifiOffIcon } from 'lucide-react';
import AppCommand from '@/components/app-command';
import { Button } from '@vitamind/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@vitamind/ui/tooltip';
import { type SocketStatus } from '@/types/realtime-plugin';
import { type WorkspacePlugin } from '@/types/workspace-plugin';
import { useFeature } from '@/hooks/use-feature';
import { usePlugin } from '@/lib/use-plugin';

export function AppHeader({
  socketStatus,
  socketReconnect,
  isRealtimePluginAvailable,
}: {
  socketStatus: SocketStatus;
  socketReconnect: () => void;
  isRealtimePluginAvailable: boolean;
}) {
  const isWorkspacesEnabled = useFeature('workspaces');
  const isWebSocketEnabled = useFeature('websocket') && isRealtimePluginAvailable;
  const { WorkspaceSwitch } = usePlugin('workspace-plugin') as WorkspacePlugin;

  return (
    <header className="bg-background -ml-1 flex h-12 shrink-0 items-center justify-between gap-2 border-b p-4 md:-ml-2">
      <div className="flex items-center">
        <SidebarTrigger className="-ml-1 md:hidden" />
        <div className="flex items-center space-x-2 text-xs">
          {isWorkspacesEnabled && WorkspaceSwitch && <WorkspaceSwitch />}
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
