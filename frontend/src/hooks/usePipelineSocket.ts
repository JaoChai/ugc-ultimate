import { useEffect, useState, useCallback, useRef } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { api, type PipelineEvent, type PipelineLog } from '../lib/api';

// Make Pusher available globally for Echo
declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: Echo<'reverb'>;
  }
}

window.Pusher = Pusher;

interface PipelineSocketState {
  connected: boolean;
  connecting: boolean;
  error: string | null;
  events: PipelineEvent[];
  logs: PipelineLog[];
  currentStep: string | null;
  progress: number;
  status: string;
}

interface UsePipelineSocketOptions {
  onProgress?: (event: PipelineEvent) => void;
  onLog?: (log: PipelineLog) => void;
  onStepCompleted?: (event: { step: string; result: Record<string, unknown>; next_step: string | null }) => void;
  autoConnect?: boolean;
}

export function usePipelineSocket(pipelineId: number | null, options: UsePipelineSocketOptions = {}) {
  const { onProgress, onLog, onStepCompleted, autoConnect = true } = options;

  const [state, setState] = useState<PipelineSocketState>({
    connected: false,
    connecting: false,
    error: null,
    events: [],
    logs: [],
    currentStep: null,
    progress: 0,
    status: 'pending',
  });

  const echoRef = useRef<Echo<'reverb'> | null>(null);
  const channelRef = useRef<number | null>(null);

  // Initialize Echo instance
  const initEcho = useCallback(() => {
    if (echoRef.current) return echoRef.current;

    const token = api.getToken();

    echoRef.current = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
      wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
      wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8080'),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${import.meta.env.VITE_API_URL}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      },
    });

    window.Echo = echoRef.current;
    return echoRef.current;
  }, []);

  // Connect to pipeline channel
  const connect = useCallback(() => {
    if (!pipelineId) return;

    setState((prev) => ({ ...prev, connecting: true, error: null }));

    try {
      const echo = initEcho();

      // Leave previous channel if any
      if (channelRef.current) {
        echo.leave(`pipeline.${channelRef.current}`);
      }

      channelRef.current = pipelineId;

      const channel = echo.private(`pipeline.${pipelineId}`);

      // Handle connection
      channel.subscribed(() => {
        setState((prev) => ({ ...prev, connected: true, connecting: false }));
      });

      channel.error((error: unknown) => {
        console.error('Pipeline channel error:', error);
        setState((prev) => ({
          ...prev,
          connected: false,
          connecting: false,
          error: 'Failed to connect to pipeline channel',
        }));
      });

      // Listen for pipeline events
      channel.listen('.pipeline.progress', (event: PipelineEvent) => {
        setState((prev) => ({
          ...prev,
          events: [...prev.events, event],
          currentStep: event.step,
          progress: event.progress,
          status: event.status,
        }));
        onProgress?.(event);
      });

      channel.listen('.pipeline.log', (log: PipelineLog) => {
        setState((prev) => ({
          ...prev,
          logs: [...prev.logs, log],
        }));
        onLog?.(log);
      });

      channel.listen('.pipeline.step.completed', (event: { step: string; result: Record<string, unknown>; next_step: string | null }) => {
        onStepCompleted?.(event);
      });
    } catch (error) {
      console.error('Failed to connect:', error);
      setState((prev) => ({
        ...prev,
        connected: false,
        connecting: false,
        error: error instanceof Error ? error.message : 'Connection failed',
      }));
    }
  }, [pipelineId, initEcho, onProgress, onLog, onStepCompleted]);

  // Disconnect from pipeline channel
  const disconnect = useCallback(() => {
    if (echoRef.current && channelRef.current) {
      echoRef.current.leave(`pipeline.${channelRef.current}`);
      channelRef.current = null;
    }
    setState((prev) => ({ ...prev, connected: false }));
  }, []);

  // Clear events and logs
  const clearEvents = useCallback(() => {
    setState((prev) => ({ ...prev, events: [], logs: [] }));
  }, []);

  // Auto-connect when pipelineId changes
  useEffect(() => {
    if (autoConnect && pipelineId) {
      connect();
    }

    return () => {
      disconnect();
    };
  }, [pipelineId, autoConnect, connect, disconnect]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (echoRef.current) {
        echoRef.current.disconnect();
        echoRef.current = null;
      }
    };
  }, []);

  return {
    ...state,
    connect,
    disconnect,
    clearEvents,
  };
}

// Hook for subscribing to specific log types
export function usePipelineLogs(pipelineId: number | null, logType?: string) {
  const [logs, setLogs] = useState<PipelineLog[]>([]);

  const handleLog = useCallback(
    (log: PipelineLog) => {
      if (!logType || log.log_type === logType) {
        setLogs((prev) => [...prev, log]);
      }
    },
    [logType]
  );

  const socket = usePipelineSocket(pipelineId, {
    onLog: handleLog,
  });

  const clearLogs = useCallback(() => {
    setLogs([]);
  }, []);

  return {
    ...socket,
    logs,
    clearLogs,
  };
}

// Hook for tracking pipeline progress
export function usePipelineProgress(pipelineId: number | null) {
  const [stepProgress, setStepProgress] = useState<Record<string, number>>({});
  const [completedSteps, setCompletedSteps] = useState<string[]>([]);

  const handleProgress = useCallback((event: PipelineEvent) => {
    setStepProgress((prev) => ({
      ...prev,
      [event.step]: event.progress,
    }));
  }, []);

  const handleStepCompleted = useCallback((event: { step: string; result: Record<string, unknown>; next_step: string | null }) => {
    setCompletedSteps((prev) => [...prev, event.step]);
    setStepProgress((prev) => ({
      ...prev,
      [event.step]: 100,
    }));
  }, []);

  const socket = usePipelineSocket(pipelineId, {
    onProgress: handleProgress,
    onStepCompleted: handleStepCompleted,
  });

  return {
    ...socket,
    stepProgress,
    completedSteps,
  };
}
