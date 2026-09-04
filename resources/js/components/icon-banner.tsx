import { cn } from '@vitamind/ui/cn';
import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

export default function IconBanner({
  icon: Icon,
  iconColor,
  circleClassName,
  tintClassName,
  title,
  description,
  className,
}: {
  icon: LucideIcon;
  iconColor: string;
  circleClassName: string;
  tintClassName: string;
  title: ReactNode;
  description: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('flex flex-col items-center gap-4 rounded-xl border p-6 text-center', tintClassName, className)}>
      <div className={cn('flex size-24 shrink-0 items-center justify-center rounded-full', circleClassName)}>
        <Icon style={{ width: '3em', height: '3em', color: iconColor }} />
      </div>
      <div className="space-y-1">
        <p className="text-foreground font-semibold">{title}</p>
        <p className="text-muted-foreground text-sm">{description}</p>
      </div>
    </div>
  );
}
