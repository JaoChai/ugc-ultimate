import { Button } from '@/components/ui/button';
import { Play, Pause, StopCircle, RotateCcw, Loader2 } from 'lucide-react';
import type { Pipeline } from '@/lib/api';

interface PipelineControlsProps {
  pipeline: Pipeline;
  onStart: () => void;
  onPause: () => void;
  onResume: () => void;
  onCancel: () => void;
  loading?: boolean;
}

export function PipelineControls({
  pipeline,
  onStart,
  onPause,
  onResume,
  onCancel,
  loading,
}: PipelineControlsProps) {
  const status = pipeline.status;

  return (
    <div className="flex items-center gap-2">
      {status === 'pending' && (
        <Button onClick={onStart} disabled={loading}>
          {loading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Play className="h-4 w-4 mr-2" />}
          Start Pipeline
        </Button>
      )}

      {status === 'running' && (
        <>
          <Button variant="outline" onClick={onPause} disabled={loading}>
            {loading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Pause className="h-4 w-4 mr-2" />}
            Pause
          </Button>
          <Button variant="destructive" onClick={onCancel} disabled={loading}>
            <StopCircle className="h-4 w-4 mr-2" />
            Cancel
          </Button>
        </>
      )}

      {status === 'paused' && (
        <>
          <Button onClick={onResume} disabled={loading}>
            {loading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RotateCcw className="h-4 w-4 mr-2" />}
            Resume
          </Button>
          <Button variant="destructive" onClick={onCancel} disabled={loading}>
            <StopCircle className="h-4 w-4 mr-2" />
            Cancel
          </Button>
        </>
      )}

      {(status === 'completed' || status === 'failed') && (
        <div className="flex items-center gap-2">
          {status === 'completed' && (
            <span className="text-sm text-green-500 font-medium">Pipeline completed successfully</span>
          )}
          {status === 'failed' && (
            <span className="text-sm text-red-500 font-medium">
              Pipeline failed: {pipeline.error_message}
            </span>
          )}
        </div>
      )}
    </div>
  );
}
