import { CheckCircle2, Circle, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { PIPELINE_STEPS, AGENT_TYPE_LABELS, type AgentType } from '@/lib/api';

interface PipelineStepsProps {
  currentStep: string | null;
  stepsState: Record<string, { status: string; progress: number }>;
  onStepClick?: (step: AgentType) => void;
  mode?: 'auto' | 'manual';
}

export function PipelineSteps({ currentStep, stepsState, onStepClick, mode = 'auto' }: PipelineStepsProps) {
  const getStepStatus = (step: string) => {
    const state = stepsState[step];
    if (!state) return 'pending';
    return state.status;
  };

  const getStepProgress = (step: string) => {
    const state = stepsState[step];
    if (!state) return 0;
    return state.progress;
  };

  const getStepIcon = (step: string) => {
    const status = getStepStatus(step);
    switch (status) {
      case 'completed':
        return <CheckCircle2 className="h-6 w-6 text-green-500" />;
      case 'running':
        return <Loader2 className="h-6 w-6 text-blue-500 animate-spin" />;
      case 'failed':
        return <Circle className="h-6 w-6 text-red-500" />;
      default:
        return <Circle className="h-6 w-6 text-muted-foreground" />;
    }
  };

  return (
    <div className="flex items-center justify-between">
      {PIPELINE_STEPS.map((step, index) => (
        <div key={step} className="flex items-center">
          <button
            onClick={() => mode === 'manual' && onStepClick?.(step)}
            disabled={mode === 'auto'}
            className={cn(
              'flex flex-col items-center gap-2 p-2 rounded-lg transition-colors',
              mode === 'manual' && 'hover:bg-muted cursor-pointer',
              currentStep === step && 'bg-muted'
            )}
          >
            <div
              className={cn(
                'flex items-center justify-center w-12 h-12 rounded-full border-2',
                getStepStatus(step) === 'completed' && 'border-green-500 bg-green-500/10',
                getStepStatus(step) === 'running' && 'border-blue-500 bg-blue-500/10',
                getStepStatus(step) === 'failed' && 'border-red-500 bg-red-500/10',
                getStepStatus(step) === 'pending' && 'border-muted-foreground/30'
              )}
            >
              {getStepIcon(step)}
            </div>
            <div className="text-center">
              <p className="text-xs font-medium">{AGENT_TYPE_LABELS[step]}</p>
              {getStepStatus(step) === 'running' && (
                <p className="text-xs text-blue-500">{getStepProgress(step)}%</p>
              )}
            </div>
          </button>
          {index < PIPELINE_STEPS.length - 1 && (
            <div
              className={cn(
                'h-0.5 w-8 mx-2',
                getStepStatus(PIPELINE_STEPS[index + 1]) !== 'pending' ||
                  getStepStatus(step) === 'completed'
                  ? 'bg-green-500'
                  : 'bg-muted-foreground/30'
              )}
            />
          )}
        </div>
      ))}
    </div>
  );
}
