import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { api, type Pipeline } from '@/lib/api';
import {
  Plus,
  Music,
  Loader2,
  Play,
  Pause,
  CheckCircle2,
  XCircle,
  Clock,
} from 'lucide-react';

const statusConfig = {
  pending: { label: 'Pending', variant: 'secondary' as const, icon: Clock },
  running: { label: 'Running', variant: 'default' as const, icon: Play },
  paused: { label: 'Paused', variant: 'outline' as const, icon: Pause },
  completed: { label: 'Completed', variant: 'default' as const, icon: CheckCircle2 },
  failed: { label: 'Failed', variant: 'destructive' as const, icon: XCircle },
};

export default function MusicVideos() {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchPipelines = async () => {
      try {
        const response = await api.pipelines.list();
        // Filter only music_video pipelines
        const musicVideoPipelines = response.data.filter(
          (p) => p.pipeline_type === 'music_video'
        );
        setPipelines(musicVideoPipelines);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to fetch pipelines');
      } finally {
        setLoading(false);
      }
    };
    fetchPipelines();
  }, []);

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-64">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Music Videos</h1>
            <p className="text-muted-foreground mt-1">
              AI-generated music videos using the Music Video Pipeline
            </p>
          </div>
          <Link to="/projects/new">
            <Button>
              <Plus className="h-4 w-4 mr-2" />
              Create Music Video
            </Button>
          </Link>
        </div>

        {error && (
          <div className="p-4 bg-destructive/10 border border-destructive/20 rounded-lg text-destructive">
            {error}
          </div>
        )}

        {!error && pipelines.length === 0 ? (
          <Card>
            <CardContent className="py-12 text-center">
              <Music className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
              <h3 className="text-lg font-medium mb-2">No Music Videos Yet</h3>
              <p className="text-muted-foreground mb-4">
                Create your first Music Video project to get started
              </p>
              <Link to="/projects/new">
                <Button>
                  <Plus className="h-4 w-4 mr-2" />
                  Create Music Video
                </Button>
              </Link>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4">
            {pipelines.map((pipeline) => {
              const status = statusConfig[pipeline.status] || statusConfig.pending;
              const StatusIcon = status.icon;
              return (
                <Link key={pipeline.id} to={`/pipelines/${pipeline.id}`}>
                  <Card className="hover:border-primary/50 transition-colors cursor-pointer">
                    <CardHeader className="pb-2">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <div className="p-2 bg-primary/10 rounded-lg">
                            <Music className="h-5 w-5 text-primary" />
                          </div>
                          <div>
                            <CardTitle className="text-base">
                              {pipeline.project?.title || `Pipeline #${pipeline.id}`}
                            </CardTitle>
                            <p className="text-xs text-muted-foreground">
                              Created {new Date(pipeline.created_at).toLocaleDateString()}
                            </p>
                          </div>
                        </div>
                        <Badge variant={status.variant} className="flex items-center gap-1">
                          <StatusIcon className="h-3 w-3" />
                          {status.label}
                        </Badge>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <div className="text-sm text-muted-foreground">
                        {pipeline.config?.song_brief ? (
                          <p className="truncate">{pipeline.config.song_brief}</p>
                        ) : pipeline.config?.theme ? (
                          <p className="truncate">{pipeline.config.theme}</p>
                        ) : (
                          <p className="italic">No song brief provided</p>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                </Link>
              );
            })}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
