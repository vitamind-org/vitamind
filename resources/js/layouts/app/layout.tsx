import { AppSidebar } from '@/components/app-sidebar';
import { AppHeader } from '@/components/app-header';
import { NavItem, SharedData } from '@/types';
import { type PropsWithChildren, useEffect, useState } from 'react';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'sonner';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useSocketEvents } from '@/hooks/use-socket-events';
import { useBroadcastChannel } from '@/hooks/use-broadcast-channel';
import { useBootstrapStore } from '@/stores/bootstrap-store';
import { Button } from '@/components/ui/button';
import { AlertCircleIcon } from 'lucide-react';
import DialogHost from '@/components/dialogs/dialog-host';

/**
 * Public channel: bootstrap config (server_provider/dns_provider/plugin
 * views) is app-wide, not tenant-scoped, so vitamind-realtime-plugin
 * broadcasts it without private-channel authorization (design.md's D6).
 * Rendered as a child of QueryClientProvider because useBroadcastChannel
 * needs useQueryClient() in context.
 */
function BootstrapBroadcastListener({ fetchBootstrap }: { fetchBootstrap: () => void }) {
  useBroadcastChannel('bootstrap', { 'bootstrap.invalidated': fetchBootstrap }, { private: false });
  return null;
}

export default function Layout({
  children,
  secondNavItems,
  secondNavTitle,
}: PropsWithChildren<{
  secondNavItems?: NavItem[];
  secondNavTitle?: string;
}>) {
  const page = usePage<SharedData>();
  const { status: socketStatus, reconnect: socketReconnect } = useSocketEvents();
  const syncBootstrap = useBootstrapStore((s) => s.syncWithServerVersion);
  const fetchBootstrap = useBootstrapStore((s) => s.fetch);
  const bootstrapConfigsLoaded = useBootstrapStore((s) => s.configs !== null);
  const bootstrapStatus = useBootstrapStore((s) => s.status);
  const serverBootstrapVersion = page.props.bootstrap_version;

  useEffect(() => {
    syncBootstrap(serverBootstrapVersion);
  }, [serverBootstrapVersion, syncBootstrap]);

  useEffect(() => {
    if (socketStatus === 'connected' && useBootstrapStore.getState().status === 'error') {
      syncBootstrap(serverBootstrapVersion);
    }
  }, [socketStatus, serverBootstrapVersion, syncBootstrap]);

  useEffect(() => {
    if (page.props.flash && page.props.flash.success) {
      toast.success(<div className="flex items-center gap-2">{page.props.flash.success}</div>);
    }
    if (page.props.flash && page.props.flash.error) {
      toast.error(<div className="flex items-center gap-2">{page.props.flash.error}</div>);
    }
    if (page.props.flash && page.props.flash.warning) {
      toast.warning(<div className="flex items-center gap-2">{page.props.flash.warning}</div>);
    }
    if (page.props.flash && page.props.flash.info) {
      toast.info(<div className="flex items-center gap-2">{page.props.flash.info}</div>);
    }
  }, [page.props.flash]);

  const [queryClient] = useState(() => new QueryClient());

  const showBootstrapError = bootstrapStatus === 'error' && !bootstrapConfigsLoaded;

  return (
    <QueryClientProvider client={queryClient}>
      <BootstrapBroadcastListener fetchBootstrap={fetchBootstrap} />
      <TooltipProvider>
        <SidebarProvider defaultOpen={!!(secondNavItems && secondNavItems.length > 0)}>
          <AppSidebar secondNavItems={secondNavItems} secondNavTitle={secondNavTitle} />
          <SidebarInset>
            <AppHeader socketStatus={socketStatus} socketReconnect={socketReconnect} />
            <div className="flex flex-1 flex-col">
              {showBootstrapError ? (
                <div className="flex flex-1 items-center justify-center p-6">
                  <div className="flex max-w-md flex-col items-center gap-4 text-center">
                    <AlertCircleIcon className="text-destructive size-8" />
                    <div>
                      <h2 className="text-lg font-semibold">Failed to load application data</h2>
                      <p className="text-muted-foreground mt-1 text-sm">
                        We couldn't reach the server to load configuration. Check your connection and try again.
                      </p>
                    </div>
                    <Button onClick={() => fetchBootstrap()}>Retry</Button>
                  </div>
                </div>
              ) : bootstrapConfigsLoaded ? (
                <>
                  {children}
                  <DialogHost />
                </>
              ) : null}
            </div>
            <Toaster richColors position="bottom-center" />
          </SidebarInset>
        </SidebarProvider>
      </TooltipProvider>
    </QueryClientProvider>
  );
}
