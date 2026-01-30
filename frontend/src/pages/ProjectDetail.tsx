import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { api } from '@/lib/api';
import type { Project, Asset } from '@/lib/api';
import {
  ArrowLeft,
  Play,
  Download,
  Music,
  Image,
  Video,
  FileVideo,
  Clock,
  CheckCircle2,
  XCircle,
  Loader2,
  Wand2,
  Sparkles,
  Eye,
} from 'lucide-react';

interface ProjectStatus {
  project: Project;
  jobs: {
    total: number;
    pending: number;
    running: number;
    completed: number;
    failed: number;
  };
  assets: {
    music: number;
    images: number;
    video_clips: number;
    final_video: number;
  };
  progress: number;
}

const statusColors: Record<string, string> = {
  draft: 'bg-gray-500',
  processing: 'bg-blue-500',
  completed: 'bg-green-500',
  failed: 'bg-red-500',
};

const statusIcons: Record<string, React.ReactNode> = {
  draft: <Clock className="h-4 w-4" />,
  processing: <Loader2 className="h-4 w-4 animate-spin" />,
  completed: <CheckCircle2 className="h-4 w-4" />,
  failed: <XCircle className="h-4 w-4" />,
};

export default function ProjectDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [status, setStatus] = useState<ProjectStatus | null>(null);
  const [assets, setAssets] = useState<Asset[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [error, setError] = useState('');

  const fetchStatus = useCallback(async () => {
    if (!id) return;
    try {
      const data = await api.projects.status(parseInt(id));
      setStatus(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch status');
    }
  }, [id]);

  const fetchAssets = useCallback(async () => {
    if (!id) return;
    try {
      const data = await api.projects.assets(parseInt(id));
      setAssets(data.assets);
    } catch (err) {
      console.error('Failed to fetch assets:', err);
    }
  }, [id]);

  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      await Promise.all([fetchStatus(), fetchAssets()]);
      setLoading(false);
    };
    loadData();
  }, [fetchStatus, fetchAssets]);

  // Auto-refresh when processing
  useEffect(() => {
    if (status?.project.status === 'processing') {
      const interval = setInterval(() => {
        fetchStatus();
        fetchAssets();
      }, 5000);
      return () => clearInterval(interval);
    }
  }, [status?.project.status, fetchStatus, fetchAssets]);

  const handleAction = async (action: string) => {
    if (!id) return;
    setActionLoading(action);
    setError('');

    try {
      const projectId = parseInt(id);
      switch (action) {
        case 'generate-concept':
          await api.projects.generateConcept(projectId, {
            theme: status?.project.title,
          });
          break;
        case 'generate-music':
          await api.projects.generateMusic(projectId, { use_concept: true });
          break;
        case 'generate-images':
          await api.projects.generateImages(projectId, { use_concept: true });
          break;
        case 'generate-videos':
          await api.projects.generateVideos(projectId, { use_concept: true });
          break;
        case 'compose':
          await api.projects.compose(projectId, {
            transitions: true,
            add_lyrics: true,
          });
          break;
        case 'generate-all':
          await api.projects.generateAll(projectId, {
            theme: status?.project.title,
            auto_compose: true,
          });
          break;
      }
      await fetchStatus();
      await fetchAssets();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Action failed');
    } finally {
      setActionLoading(null);
    }
  };

  const handleDownload = async () => {
    if (!id) return;
    try {
      const data = await api.projects.download(parseInt(id));
      window.open(data.download_url, '_blank');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Download failed');
    }
  };

  const getAssetsByType = (type: string) => assets.filter((a) => a.type === type);

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-64">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      </DashboardLayout>
    );
  }

  if (!status) {
    return (
      <DashboardLayout>
        <div className="text-center py-12">
          <p className="text-muted-foreground">Project not found</p>
          <Button variant="outline" className="mt-4" onClick={() => navigate('/projects')}>
            Back to Projects
          </Button>
        </div>
      </DashboardLayout>
    );
  }

  const project = status.project;
  const hasConcept = !!project.concept;
  const hasMusic = status.assets.music > 0;
  const hasImages = status.assets.images > 0;
  const hasVideos = status.assets.video_clips > 0;
  const hasFinalVideo = status.assets.final_video > 0;

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="icon" onClick={() => navigate('/projects')}>
              <ArrowLeft className="h-5 w-5" />
            </Button>
            <div>
              <h1 className="text-2xl font-bold">{project.title}</h1>
              <div className="flex items-center gap-2 mt-1">
                <Badge className={statusColors[project.status]}>
                  <span className="flex items-center gap-1">
                    {statusIcons[project.status]}
                    {project.status}
                  </span>
                </Badge>
                {project.description && (
                  <span className="text-sm text-muted-foreground">{project.description}</span>
                )}
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2">
            {hasFinalVideo && (
              <>
                <Button variant="outline" onClick={handleDownload}>
                  <Download className="h-4 w-4 mr-2" />
                  Download
                </Button>
                <Button>
                  <Play className="h-4 w-4 mr-2" />
                  Preview
                </Button>
              </>
            )}
          </div>
        </div>

        {error && (
          <div className="p-4 bg-destructive/10 border border-destructive/20 rounded-lg text-destructive">
            {error}
          </div>
        )}

        {/* Progress */}
        {project.status === 'processing' && (
          <Card>
            <CardContent className="pt-6">
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>Generation Progress</span>
                  <span>{status.progress}%</span>
                </div>
                <Progress value={status.progress} />
                <div className="flex justify-between text-xs text-muted-foreground">
                  <span>
                    {status.jobs.completed} of {status.jobs.total} jobs completed
                  </span>
                  {status.jobs.running > 0 && <span>{status.jobs.running} running</span>}
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Generation Pipeline</CardTitle>
            <CardDescription>Generate content step by step or all at once</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-3">
              {!hasConcept && project.status !== 'processing' && (
                <Button
                  variant="outline"
                  onClick={() => handleAction('generate-all')}
                  disabled={!!actionLoading}
                >
                  {actionLoading === 'generate-all' ? (
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <Sparkles className="h-4 w-4 mr-2" />
                  )}
                  Generate All
                </Button>
              )}

              <Button
                variant="outline"
                onClick={() => handleAction('generate-concept')}
                disabled={!!actionLoading || project.status === 'processing'}
              >
                {actionLoading === 'generate-concept' ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <Wand2 className="h-4 w-4 mr-2" />
                )}
                {hasConcept ? 'Regenerate' : 'Generate'} Concept
              </Button>

              <Button
                variant="outline"
                onClick={() => handleAction('generate-music')}
                disabled={!hasConcept || !!actionLoading || project.status === 'processing'}
              >
                {actionLoading === 'generate-music' ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <Music className="h-4 w-4 mr-2" />
                )}
                Generate Music
              </Button>

              <Button
                variant="outline"
                onClick={() => handleAction('generate-images')}
                disabled={!hasConcept || !!actionLoading || project.status === 'processing'}
              >
                {actionLoading === 'generate-images' ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <Image className="h-4 w-4 mr-2" />
                )}
                Generate Images
              </Button>

              <Button
                variant="outline"
                onClick={() => handleAction('generate-videos')}
                disabled={!hasImages || !!actionLoading || project.status === 'processing'}
              >
                {actionLoading === 'generate-videos' ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <Video className="h-4 w-4 mr-2" />
                )}
                Generate Videos
              </Button>

              <Button
                onClick={() => handleAction('compose')}
                disabled={
                  !hasMusic || !hasVideos || !!actionLoading || project.status === 'processing'
                }
              >
                {actionLoading === 'compose' ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <FileVideo className="h-4 w-4 mr-2" />
                )}
                Compose Final Video
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Content Tabs */}
        <Tabs defaultValue="assets" className="space-y-4">
          <TabsList>
            <TabsTrigger value="assets">Assets ({assets.length})</TabsTrigger>
            <TabsTrigger value="concept">Concept</TabsTrigger>
            <TabsTrigger value="jobs">Jobs</TabsTrigger>
          </TabsList>

          <TabsContent value="assets" className="space-y-4">
            {/* Final Video */}
            {hasFinalVideo && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-lg flex items-center gap-2">
                    <FileVideo className="h-5 w-5" />
                    Final Video
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid gap-4">
                    {getAssetsByType('final_video').map((asset) => (
                      <div
                        key={asset.id}
                        className="flex items-center justify-between p-4 bg-muted rounded-lg"
                      >
                        <div className="flex items-center gap-4">
                          <div className="w-32 h-20 bg-black rounded overflow-hidden">
                            {getAssetsByType('thumbnail')[0] && (
                              <img
                                src={getAssetsByType('thumbnail')[0].url}
                                alt="Thumbnail"
                                className="w-full h-full object-cover"
                              />
                            )}
                          </div>
                          <div>
                            <p className="font-medium">{asset.filename}</p>
                            <p className="text-sm text-muted-foreground">
                              {asset.duration_seconds}s •{' '}
                              {(asset.size_bytes / 1024 / 1024).toFixed(1)} MB
                            </p>
                          </div>
                        </div>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" asChild>
                            <a href={asset.url} target="_blank" rel="noopener noreferrer">
                              <Eye className="h-4 w-4 mr-1" />
                              Preview
                            </a>
                          </Button>
                          <Button size="sm" onClick={handleDownload}>
                            <Download className="h-4 w-4 mr-1" />
                            Download
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Music */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <Music className="h-5 w-5" />
                  Music ({status.assets.music})
                </CardTitle>
              </CardHeader>
              <CardContent>
                {hasMusic ? (
                  <div className="space-y-2">
                    {getAssetsByType('music').map((asset) => (
                      <div
                        key={asset.id}
                        className="flex items-center justify-between p-3 bg-muted rounded-lg"
                      >
                        <div className="flex items-center gap-3">
                          <Music className="h-8 w-8 text-muted-foreground" />
                          <div>
                            <p className="font-medium">{asset.filename}</p>
                            <p className="text-sm text-muted-foreground">
                              {asset.duration_seconds}s
                            </p>
                          </div>
                        </div>
                        <audio controls src={asset.url} className="h-8" />
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted-foreground text-sm">No music generated yet</p>
                )}
              </CardContent>
            </Card>

            {/* Images */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <Image className="h-5 w-5" />
                  Images ({status.assets.images})
                </CardTitle>
              </CardHeader>
              <CardContent>
                {hasImages ? (
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {getAssetsByType('image').map((asset) => (
                      <div key={asset.id} className="group relative aspect-video rounded-lg overflow-hidden bg-muted">
                        <img
                          src={asset.url}
                          alt={asset.filename}
                          className="w-full h-full object-cover"
                        />
                        <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                          <Button variant="secondary" size="sm" asChild>
                            <a href={asset.url} target="_blank" rel="noopener noreferrer">
                              <Eye className="h-4 w-4" />
                            </a>
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted-foreground text-sm">No images generated yet</p>
                )}
              </CardContent>
            </Card>

            {/* Video Clips */}
            <Card>
              <CardHeader>
                <CardTitle className="text-lg flex items-center gap-2">
                  <Video className="h-5 w-5" />
                  Video Clips ({status.assets.video_clips})
                </CardTitle>
              </CardHeader>
              <CardContent>
                {hasVideos ? (
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    {getAssetsByType('video_clip').map((asset) => (
                      <div key={asset.id} className="space-y-2">
                        <video
                          src={asset.url}
                          controls
                          className="w-full aspect-video rounded-lg bg-black"
                        />
                        <p className="text-sm text-muted-foreground truncate">{asset.filename}</p>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted-foreground text-sm">No video clips generated yet</p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="concept">
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">AI Generated Concept</CardTitle>
              </CardHeader>
              <CardContent>
                {hasConcept && project.concept ? (
                  <div className="space-y-6">
                    {project.concept?.music && (
                      <div>
                        <h4 className="font-medium mb-2">Music Concept</h4>
                        <dl className="grid grid-cols-2 gap-2 text-sm">
                          <dt className="text-muted-foreground">Title</dt>
                          <dd>{project.concept?.music?.title}</dd>
                          <dt className="text-muted-foreground">Genre</dt>
                          <dd>{project.concept?.music?.genre}</dd>
                          <dt className="text-muted-foreground">Mood</dt>
                          <dd>{project.concept?.music?.mood}</dd>
                          <dt className="text-muted-foreground">BPM</dt>
                          <dd>{project.concept?.music?.bpm}</dd>
                        </dl>
                      </div>
                    )}

                    {project.concept?.lyrics && (
                      <div>
                        <h4 className="font-medium mb-2">Lyrics</h4>
                        <pre className="text-sm bg-muted p-4 rounded-lg whitespace-pre-wrap font-sans">
                          {project.concept?.lyrics}
                        </pre>
                      </div>
                    )}

                    {project.concept?.visual?.scenes && (
                      <div>
                        <h4 className="font-medium mb-2">Visual Scenes</h4>
                        <div className="space-y-3">
                          {project.concept?.visual?.scenes.map((scene: any, i: number) => (
                            <div key={i} className="p-3 bg-muted rounded-lg">
                              <p className="font-medium text-sm">Scene {i + 1}</p>
                              <p className="text-sm text-muted-foreground mt-1">
                                {scene.description}
                              </p>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                ) : (
                  <p className="text-muted-foreground">
                    No concept generated yet. Click "Generate Concept" to start.
                  </p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="jobs">
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Job Status</CardTitle>
                <CardDescription>
                  {status.jobs.total} total jobs • {status.jobs.completed} completed •{' '}
                  {status.jobs.failed} failed
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {project.job_logs?.map((log: any) => (
                    <div
                      key={log.id}
                      className="flex items-center justify-between p-3 bg-muted rounded-lg"
                    >
                      <div className="flex items-center gap-3">
                        {log.status === 'completed' && (
                          <CheckCircle2 className="h-5 w-5 text-green-500" />
                        )}
                        {log.status === 'running' && (
                          <Loader2 className="h-5 w-5 text-blue-500 animate-spin" />
                        )}
                        {log.status === 'failed' && <XCircle className="h-5 w-5 text-red-500" />}
                        {log.status === 'pending' && (
                          <Clock className="h-5 w-5 text-muted-foreground" />
                        )}
                        <div>
                          <p className="font-medium text-sm">{log.job_type}</p>
                          {log.error_message && (
                            <p className="text-xs text-red-500">{log.error_message}</p>
                          )}
                        </div>
                      </div>
                      <Badge variant="outline">{log.status}</Badge>
                    </div>
                  )) || <p className="text-muted-foreground text-sm">No jobs yet</p>}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </DashboardLayout>
  );
}
