import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import { PipelineSteps } from '@/components/pipeline/PipelineSteps';
import { PipelineLogs } from '@/components/pipeline/PipelineLogs';
import { PipelineControls } from '@/components/pipeline/PipelineControls';
import { StepResultPanel } from '@/components/pipeline/StepResultPanel';
import { usePipelineSocket } from '@/hooks/usePipelineSocket';
import { pipelinesApi, type Pipeline, type PipelineStepState } from '@/lib/api';
import { ArrowLeft, Loader2, Wifi, WifiOff, RefreshCw } from 'lucide-react';

export default function PipelineMonitor() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [pipeline, setPipeline] = useState<Pipeline | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState('');

  const pipelineId = id ? parseInt(id) : null;

  // WebSocket connection
  const {
    connected,
    connecting,
    logs,
    currentStep,
    progress,
    clearEvents,
  } = usePipelineSocket(pipelineId, {
    onProgress: () => {
      // Update local pipeline state from socket events
      setPipeline((prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          current_step: currentStep,
          current_step_progress: progress,
        };
      });
    },
    onStepCompleted: () => {
      // Refresh pipeline data when step completes
      fetchPipeline();
    },
  });

  const fetchPipeline = useCallback(async () => {
    if (!pipelineId) return;
    try {
      const response = await pipelinesApi.get(pipelineId);
      setPipeline(response.pipeline);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch pipeline');
    } finally {
      setLoading(false);
    }
  }, [pipelineId]);

  useEffect(() => {
    fetchPipeline();
  }, [fetchPipeline]);

  const handleStart = async () => {
    if (!pipelineId) return;
    setActionLoading(true);
    setError('');
    try {
      const response = await pipelinesApi.start(pipelineId);
      setPipeline(response.pipeline);
      clearEvents();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to start pipeline');
    } finally {
      setActionLoading(false);
    }
  };

  const handlePause = async () => {
    if (!pipelineId) return;
    setActionLoading(true);
    try {
      const response = await pipelinesApi.pause(pipelineId);
      setPipeline(response.pipeline);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to pause pipeline');
    } finally {
      setActionLoading(false);
    }
  };

  const handleResume = async () => {
    if (!pipelineId) return;
    setActionLoading(true);
    try {
      const response = await pipelinesApi.resume(pipelineId);
      setPipeline(response.pipeline);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to resume pipeline');
    } finally {
      setActionLoading(false);
    }
  };

  const handleCancel = async () => {
    if (!pipelineId) return;
    setActionLoading(true);
    try {
      const response = await pipelinesApi.cancel(pipelineId);
      setPipeline(response.pipeline);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to cancel pipeline');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRunStep = async (step: string) => {
    if (!pipelineId) return;
    setActionLoading(true);
    setError('');
    try {
      const response = await pipelinesApi.runStep(pipelineId, step);
      setPipeline(response.pipeline);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to run step');
    } finally {
      setActionLoading(false);
    }
  };

  // Build steps state for display
  const getStepsState = (): Record<string, PipelineStepState> => {
    if (!pipeline) return {};

    const stepsState = pipeline.steps_state || {};

    // If running, update current step progress from socket
    if (pipeline.status === 'running' && currentStep) {
      return {
        ...stepsState,
        [currentStep]: {
          ...stepsState[currentStep],
          status: 'running',
          progress: progress,
        },
      };
    }

    return stepsState;
  };

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-64">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      </DashboardLayout>
    );
  }

  if (!pipeline) {
    return (
      <DashboardLayout>
        <div className="text-center py-12">
          <p className="text-muted-foreground">Pipeline not found</p>
          <Button variant="outline" className="mt-4" onClick={() => navigate('/projects')}>
            Back to Projects
          </Button>
        </div>
      </DashboardLayout>
    );
  }

  const stepsState = getStepsState();
  const displayCurrentStep = currentStep || pipeline.current_step;
  const displayProgress = pipeline.status === 'running' ? progress : pipeline.current_step_progress;

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="icon" onClick={() => navigate(`/projects/${pipeline.project_id}`)}>
              <ArrowLeft className="h-5 w-5" />
            </Button>
            <div>
              <h1 className="text-2xl font-bold">Pipeline Monitor</h1>
              <div className="flex items-center gap-3 mt-1">
                <span className="text-sm text-muted-foreground">
                  Mode: {pipeline.mode === 'auto' ? 'Automatic' : 'Manual'}
                </span>
                <div className="flex items-center gap-1.5">
                  {connected ? (
                    <>
                      <Wifi className="h-4 w-4 text-green-500" />
                      <span className="text-xs text-green-500">Connected</span>
                    </>
                  ) : connecting ? (
                    <>
                      <Loader2 className="h-4 w-4 text-yellow-500 animate-spin" />
                      <span className="text-xs text-yellow-500">Connecting...</span>
                    </>
                  ) : (
                    <>
                      <WifiOff className="h-4 w-4 text-muted-foreground" />
                      <span className="text-xs text-muted-foreground">Disconnected</span>
                    </>
                  )}
                </div>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={fetchPipeline}>
              <RefreshCw className="h-4 w-4 mr-2" />
              Refresh
            </Button>
            <PipelineControls
              pipeline={pipeline}
              onStart={handleStart}
              onPause={handlePause}
              onResume={handleResume}
              onCancel={handleCancel}
              loading={actionLoading}
            />
          </div>
        </div>

        {error && (
          <div className="p-4 bg-destructive/10 border border-destructive/20 rounded-lg text-destructive">
            {error}
          </div>
        )}

        {/* Pipeline Steps Visualization */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-lg">Pipeline Progress</CardTitle>
          </CardHeader>
          <CardContent>
            <PipelineSteps
              currentStep={displayCurrentStep}
              stepsState={stepsState}
              onStepClick={pipeline.mode === 'manual' ? handleRunStep : undefined}
              mode={pipeline.mode}
            />
          </CardContent>
        </Card>

        {/* Current Step Progress */}
        {pipeline.status === 'running' && displayCurrentStep && (
          <Card>
            <CardContent className="pt-6">
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>Current Step: {displayCurrentStep}</span>
                  <span>{displayProgress}%</span>
                </div>
                <Progress value={displayProgress} />
              </div>
            </CardContent>
          </Card>
        )}

        {/* Tabs: Logs / Results */}
        <Tabs defaultValue="logs" className="space-y-4">
          <TabsList>
            <TabsTrigger value="logs">Live Logs</TabsTrigger>
            <TabsTrigger value="results">Step Results</TabsTrigger>
          </TabsList>

          <TabsContent value="logs">
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Activity Log</CardTitle>
              </CardHeader>
              <CardContent>
                <PipelineLogs logs={logs} maxHeight="400px" autoScroll />
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="results">
            <StepResultPanel stepsState={stepsState} />
          </TabsContent>
        </Tabs>
      </div>
    </DashboardLayout>
  );
}
