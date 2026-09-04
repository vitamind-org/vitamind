import { ReactNode } from 'react';
import { cn } from '@vitamind/ui/cn';

export default function Container({ className, children }: { className?: string; children?: ReactNode }) {
  return <div className={cn('container mx-auto space-y-5 px-4 py-5', className)}>{children}</div>;
}
