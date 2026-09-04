import { ComponentType, SVGProps } from 'react';
import { Button } from '@vitamind/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@vitamind/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@vitamind/ui/tooltip';
import { CheckIcon, ChevronDownIcon, TriangleAlertIcon, Users } from 'lucide-react';

// 'app' (anyone signed in) and 'public' are deferred — see design.md Non-Goals.
export type Visibility = 'user' | 'workspace';

export const VISIBILITY_LABELS: Record<Visibility, string> = {
  user: 'Only me',
  workspace: 'Workspace',
};

/**
 * lucide-react's `user-lock` icon isn't in the pinned 0.475.0 release (it
 * landed upstream later) — inlined here (same path data as lucide's own)
 * rather than bumping a shared dependency app-wide for one glyph.
 */
function UserLockIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" {...props}>
      <circle cx="10" cy="7" r="4" />
      <path d="M10.3 15H7a4 4 0 0 0-4 4v2" />
      <path d="M15 15.5V14a2 2 0 0 1 4 0v1.5" />
      <rect width="8" height="5" x="13" y="16" rx=".899" />
    </svg>
  );
}

const VISIBILITY_ICON_COMPONENTS: Record<Visibility, ComponentType<SVGProps<SVGSVGElement>>> = {
  user: UserLockIcon,
  workspace: Users,
};

export function VisibilityIcon({ visibility, className = 'size-4' }: { visibility: Visibility; className?: string }) {
  const Icon = VISIBILITY_ICON_COMPONENTS[visibility];
  return <Icon className={className} />;
}

/**
 * An item is unreachable via its own declared visibility when its parent
 * is narrower — e.g. a `workspace` item inside a `user` (Only me) folder.
 * Non-owners can't open the parent to begin with, so the child's broader
 * setting is meaningless in practice.
 */
function isBrokenVisibility(value: Visibility, parentVisibility: Visibility): boolean {
  return parentVisibility === 'user' && value === 'workspace';
}

/**
 * lucide's "combining icons" pattern (lucide.dev/guide/react/advanced/combining-icons):
 * the item's own visibility icon with a small triangle-alert badge overlaid
 * on its corner — one glyph that reads as "this visibility has a problem",
 * rather than two unrelated icons sitting side by side.
 */
function VisibilityConflictIcon({ visibility, className = 'size-4' }: { visibility: Visibility; className?: string }) {
  return (
    <span className={`relative inline-flex items-center justify-center ${className}`}>
      <VisibilityIcon visibility={visibility} className="size-full" />
      <TriangleAlertIcon className="text-warning bg-background absolute -right-1 -bottom-1 size-3 rounded-full" />
    </span>
  );
}

export default function VisibilitySelect({
  value,
  onChange,
  disabled,
  iconOnly = false,
  parentVisibility,
}: {
  value: Visibility;
  onChange: (value: Visibility) => void;
  disabled?: boolean;
  iconOnly?: boolean;
  /** When given, flags — with a combined icon + tooltip — a `value` unreachable under this ancestor's visibility. */
  parentVisibility?: Visibility;
}) {
  const broken = parentVisibility !== undefined && isBrokenVisibility(value, parentVisibility);
  const icon = broken ? <VisibilityConflictIcon visibility={value} /> : <VisibilityIcon visibility={value} />;

  const button = iconOnly ? (
    <Button type="button" variant="ghost" size="icon" disabled={disabled} aria-label={`Visibility: ${VISIBILITY_LABELS[value]}`}>
      {icon}
    </Button>
  ) : (
    <Button type="button" variant="outline" size="sm" disabled={disabled} className="w-40 justify-between font-normal">
      <span className="flex items-center gap-2">
        {icon}
        {VISIBILITY_LABELS[value]}
      </span>
      <ChevronDownIcon className="size-4 opacity-50" />
    </Button>
  );

  const trigger = <DropdownMenuTrigger asChild>{button}</DropdownMenuTrigger>;

  // A tooltip repeating the label the button already shows would be
  // redundant in the labeled (non-iconOnly) variant — only the conflict
  // explanation still earns its place there, since that's not shown
  // anywhere else.
  const showTooltip = broken || iconOnly;

  return (
    <DropdownMenu>
      {showTooltip ? (
        <Tooltip>
          <TooltipTrigger asChild>{trigger}</TooltipTrigger>
          <TooltipContent className={broken ? 'max-w-64' : undefined}>
            {broken ? (
              <>
                <p className="font-medium">Visibility conflict</p>
                <p className="text-muted-foreground mt-1">
                  This item is set to <strong>{VISIBILITY_LABELS[value]}</strong>, but its parent folder is set to{' '}
                  <strong>{VISIBILITY_LABELS[parentVisibility!]}</strong>. Other workspace members can&apos;t open
                  the parent folder, so they&apos;ll never be able to reach this item even though it&apos;s shared
                  more broadly.
                </p>
              </>
            ) : (
              VISIBILITY_LABELS[value]
            )}
          </TooltipContent>
        </Tooltip>
      ) : (
        trigger
      )}
      <DropdownMenuContent align="end" className="w-40">
        {(Object.keys(VISIBILITY_LABELS) as Visibility[]).map((visibility) => (
          <DropdownMenuItem key={visibility} onClick={() => onChange(visibility)}>
            <VisibilityIcon visibility={visibility} />
            {VISIBILITY_LABELS[visibility]}
            {visibility === value && <CheckIcon className="ml-auto size-4" />}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
