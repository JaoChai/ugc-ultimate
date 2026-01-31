import { memo, useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';
import type { PipelineLog } from '@/lib/api';
import { getAgentTypeLabel } from '@/lib/api';
import { Brain, Info, AlertCircle, CheckCircle2, TrendingUp } from 'lucide-react';

interface PipelineLogsProps {
  logs: PipelineLog[];
  maxHeight?: string;
  autoScroll?: boolean;
}

const logTypeIcons: Record<string, React.ReactNode> = {
  info: <Info className="h-4 w-4 text-blue-500" />,
  progress: <TrendingUp className="h-4 w-4 text-yellow-500" />,
  result: <CheckCircle2 className="h-4 w-4 text-green-500" />,
  error: <AlertCircle className="h-4 w-4 text-red-500" />,
  thinking: <Brain className="h-4 w-4 text-purple-500" />,
};

const logTypeColors: Record<string, string> = {
  info: 'border-blue-500/20 bg-blue-500/5',
  progress: 'border-yellow-500/20 bg-yellow-500/5',
  result: 'border-green-500/20 bg-green-500/5',
  error: 'border-red-500/20 bg-red-500/5',
  thinking: 'border-purple-500/20 bg-purple-500/5',
};

export const PipelineLogs = memo(function PipelineLogs({ logs, maxHeight = '400px', autoScroll = true }: PipelineLogsProps) {
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (autoScroll && scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [logs, autoScroll]);

  if (logs.length === 0) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        <p>No logs yet. Start the pipeline to see activity.</p>
      </div>
    );
  }

  return (
    <div
      ref={scrollRef}
      className="space-y-2 overflow-y-auto"
      style={{ maxHeight }}
    >
      {logs.map((log, index) => (
        <div
          key={log.id || index}
          className={cn(
            'p-3 rounded-lg border',
            logTypeColors[log.log_type] || 'border-muted bg-muted/50'
          )}
        >
          <div className="flex items-start gap-2">
            {logTypeIcons[log.log_type] || <Info className="h-4 w-4 text-muted-foreground" />}
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                <span className="font-medium">
                  {getAgentTypeLabel(log.agent_type)}
                </span>
                <span>•</span>
                <span>{new Date(log.created_at).toLocaleTimeString()}</span>
              </div>
              <p className="text-sm">{log.message}</p>
              {log.data && log.log_type === 'progress' && log.data.progress !== undefined && (
                <div className="mt-2">
                  <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                    <div
                      className="h-full bg-blue-500 transition-all duration-300"
                      style={{ width: `${log.data.progress}%` }}
                    />
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      ))}
    </div>
  );
});
