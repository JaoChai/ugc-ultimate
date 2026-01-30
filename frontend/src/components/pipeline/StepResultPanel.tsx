import { memo, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AGENT_TYPE_LABELS, type AgentType, type PipelineStepState } from '@/lib/api';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface StepResultPanelProps {
  stepsState: Record<string, PipelineStepState>;
}

const statusColors: Record<string, string> = {
  pending: 'bg-gray-500',
  running: 'bg-blue-500',
  completed: 'bg-green-500',
  failed: 'bg-red-500',
};

export const StepResultPanel = memo(function StepResultPanel({ stepsState }: StepResultPanelProps) {
  const [expandedSteps, setExpandedSteps] = useState<Set<string>>(new Set());

  const toggleStep = (step: string) => {
    setExpandedSteps((prev) => {
      const next = new Set(prev);
      if (next.has(step)) {
        next.delete(step);
      } else {
        next.add(step);
      }
      return next;
    });
  };

  const steps = Object.entries(stepsState).filter(([, state]) => state.status !== 'pending');

  if (steps.length === 0) {
    return (
      <Card>
        <CardContent className="py-8 text-center text-muted-foreground">
          No step results yet. Start the pipeline to see results.
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-2">
      {steps.map(([step, state]) => (
        <Card key={step}>
          <CardHeader
            className="cursor-pointer py-3"
            onClick={() => toggleStep(step)}
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                {expandedSteps.has(step) ? (
                  <ChevronDown className="h-4 w-4" />
                ) : (
                  <ChevronRight className="h-4 w-4" />
                )}
                <CardTitle className="text-sm font-medium">
                  {AGENT_TYPE_LABELS[step as AgentType] || step}
                </CardTitle>
              </div>
              <Badge className={cn('text-xs', statusColors[state.status])}>
                {state.status}
              </Badge>
            </div>
          </CardHeader>
          {expandedSteps.has(step) && state.result && (
            <CardContent className="pt-0">
              <ResultDisplay result={state.result} step={step} />
            </CardContent>
          )}
        </Card>
      ))}
    </div>
  );
});

// Memoize ResultDisplay for better performance
const ResultDisplay = memo(function ResultDisplay({ result, step }: { result: Record<string, any>; step: string }) {
  // Customize display based on step type
  switch (step) {
    case 'theme_director':
      return (
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-muted-foreground">Title:</span>
              <p className="font-medium">{result.title}</p>
            </div>
            <div>
              <span className="text-muted-foreground">Mood:</span>
              <p className="font-medium">{result.mood}</p>
            </div>
            <div>
              <span className="text-muted-foreground">Style:</span>
              <p className="font-medium">{result.style}</p>
            </div>
            <div>
              <span className="text-muted-foreground">Target Audience:</span>
              <p className="font-medium">{result.target_audience}</p>
            </div>
          </div>
          {result.keywords && (
            <div>
              <span className="text-muted-foreground text-sm">Keywords:</span>
              <div className="flex flex-wrap gap-1 mt-1">
                {result.keywords.map((keyword: string, i: number) => (
                  <Badge key={i} variant="outline" className="text-xs">
                    {keyword}
                  </Badge>
                ))}
              </div>
            </div>
          )}
        </div>
      );

    case 'music_composer':
      return (
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-muted-foreground">Title:</span>
              <p className="font-medium">{result.title}</p>
            </div>
            <div>
              <span className="text-muted-foreground">Genre:</span>
              <p className="font-medium">{result.genre}</p>
            </div>
            <div>
              <span className="text-muted-foreground">BPM:</span>
              <p className="font-medium">{result.bpm}</p>
            </div>
          </div>
          {result.audio_url && (
            <div>
              <span className="text-muted-foreground text-sm">Audio:</span>
              <audio controls src={result.audio_url} className="w-full mt-1" />
            </div>
          )}
        </div>
      );

    case 'visual_director':
      return (
        <div className="space-y-3">
          <div className="text-sm">
            <span className="text-muted-foreground">Scenes: </span>
            <span className="font-medium">{result.scene_count || result.scenes?.length || 0}</span>
          </div>
          {result.style_guide && (
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span className="text-muted-foreground">Art Style:</span>
                <p className="font-medium">{result.style_guide.art_style}</p>
              </div>
            </div>
          )}
        </div>
      );

    case 'image_generator':
      return (
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-muted-foreground">Generated:</span>
              <p className="font-medium text-green-500">{result.total_generated}</p>
            </div>
            {result.total_failed > 0 && (
              <div>
                <span className="text-muted-foreground">Failed:</span>
                <p className="font-medium text-red-500">{result.total_failed}</p>
              </div>
            )}
          </div>
          {result.images && result.images.length > 0 && (
            <div className="grid grid-cols-4 gap-2">
              {result.images.slice(0, 8).map((img: any, i: number) => (
                <img
                  key={i}
                  src={img.image_url || img.url}
                  alt={`Scene ${img.scene_number}`}
                  className="aspect-video object-cover rounded"
                />
              ))}
            </div>
          )}
        </div>
      );

    case 'video_composer':
      return (
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-muted-foreground">Duration:</span>
              <p className="font-medium">{result.total_duration}s</p>
            </div>
            <div>
              <span className="text-muted-foreground">Images:</span>
              <p className="font-medium">{result.images?.length || 0}</p>
            </div>
          </div>
        </div>
      );

    default:
      return (
        <pre className="text-xs bg-muted p-3 rounded-lg overflow-auto max-h-48">
          {JSON.stringify(result, null, 2)}
        </pre>
      );
  }
});
