import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { useEffect, useState, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { CommandIcon, SearchIcon, UserIcon, SettingsIcon, UsersIcon, MoonIcon, SunIcon, MonitorIcon } from 'lucide-react';
import { usePage, router } from '@inertiajs/react';
import { useAppearance } from '@/hooks/use-appearance';
import { SharedData } from '@/types';

export default function AppCommand() {
  const [open, setOpen] = useState(false);
  const { props } = usePage<SharedData>();
  const { updateAppearance } = useAppearance();

  const isProjectsEnabled = useMemo(() => {
    const features = props.features as Record<string, boolean> | undefined;
    return !!(features && features.projects);
  }, [props.features]);

  useEffect(() => {
    const down = (e: KeyboardEvent) => {
      if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        setOpen((open) => !open);
      }
    };

    document.addEventListener('keydown', down);
    return () => document.removeEventListener('keydown', down);
  }, []);

  const handleOpenChange = (open: boolean) => {
    setOpen(open);
  };

  const navigateTo = (routeName: string) => {
    // Check if route exists before navigation
    // @ts-ignore
    if (typeof route !== 'undefined' && typeof route().has === 'function' && route().has(routeName)) {
      router.visit(route(routeName));
      setOpen(false);
    }
  };

  return (
    <div>
      <Button className="hidden px-1! lg:flex" variant="outline" size="sm" onClick={() => setOpen(true)}>
        <span className="sr-only">Open command menu</span>
        <SearchIcon className="ml-1 size-3" />
        Search...
        <span className="bg-accent flex h-6 items-center justify-center rounded-sm border px-2 text-xs">
          <CommandIcon className="mr-1 size-3" /> K
        </span>
      </Button>
      <Button className="lg:hidden" variant="outline" size="sm" onClick={() => setOpen(true)}>
        <CommandIcon className="mr-1 size-3" /> K
      </Button>
      <CommandDialog open={open} onOpenChange={handleOpenChange}>
        <CommandInput placeholder="Type a command or search..." />
        <CommandList>
          <CommandEmpty>No results found.</CommandEmpty>
          
          <CommandGroup heading="Navigation">
            <CommandItem value="go-to-profile" onSelect={() => navigateTo('profile')}>
              <UserIcon className="mr-2 size-4" />
              Go to Profile
            </CommandItem>
            <CommandItem value="go-to-settings" onSelect={() => navigateTo('settings')}>
              <SettingsIcon className="mr-2 size-4" />
              Go to Settings
            </CommandItem>
            <CommandItem value="go-to-users" onSelect={() => navigateTo('users')}>
              <UsersIcon className="mr-2 size-4" />
              Go to Users
            </CommandItem>
            {isProjectsEnabled && (
              <CommandItem value="go-to-projects" onSelect={() => navigateTo('projects')}>
                <ListIcon className="mr-2 size-4" />
                Go to Projects
              </CommandItem>
            )}
          </CommandGroup>

          <CommandGroup heading="Appearance / Theme">
            <CommandItem value="theme-light" onSelect={() => { updateAppearance('light'); setOpen(false); }}>
              <SunIcon className="mr-2 size-4" />
              Switch to Light Theme
            </CommandItem>
            <CommandItem value="theme-dark" onSelect={() => { updateAppearance('dark'); setOpen(false); }}>
              <MoonIcon className="mr-2 size-4" />
              Switch to Dark Theme
            </CommandItem>
            <CommandItem value="theme-system" onSelect={() => { updateAppearance('system'); setOpen(false); }}>
              <MonitorIcon className="mr-2 size-4" />
              Switch to System Theme
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </CommandDialog>
    </div>
  );
}

// Stub ListIcon in case it's not imported
const ListIcon = ({ className }: { className?: string }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
    className={className}
  >
    <line x1="8" y1="6" x2="21" y2="6"></line>
    <line x1="8" y1="12" x2="21" y2="12"></line>
    <line x1="8" y1="18" x2="21" y2="18"></line>
    <line x1="3" y1="6" x2="3.01" y2="6"></line>
    <line x1="3" y1="12" x2="3.01" y2="12"></line>
    <line x1="3" y1="18" x2="3.01" y2="18"></line>
  </svg>
);
