import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors',
  {
    variants: {
      variant: {
        default: 'border-slate-200 bg-white text-slate-600',
        secondary: 'border-slate-200 bg-slate-50 text-slate-600',
        destructive: 'border-red-200 bg-red-50 text-red-600',
        success: 'border-slate-200 bg-white text-slate-600',
        outline: 'border-slate-200 text-slate-600',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
);

// Status dot colors for visual indicator
const statusDotColors = {
  draft: 'bg-slate-400',
  processing: 'bg-blue-500',
  completed: 'bg-green-500',
  failed: 'bg-red-500',
  active: 'bg-green-500',
  paused: 'bg-slate-400',
};

export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {
  status?: keyof typeof statusDotColors;
}

function Badge({ className, variant, status, children, ...props }: BadgeProps) {
  return (
    <div className={cn(badgeVariants({ variant }), className)} {...props}>
      {status && (
        <span className={cn('h-1.5 w-1.5 rounded-full', statusDotColors[status])} />
      )}
      {children}
    </div>
  );
}

export { Badge, badgeVariants, statusDotColors };
