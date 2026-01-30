import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { api } from '@/lib/api';
import {
  Wand2,
  Music,
  Video,
  Image,
  Sparkles,
  ChevronRight,
  ChevronLeft,
  Loader2,
  Check,
} from 'lucide-react';

type Step = 'basic' | 'concept' | 'options' | 'review';

interface ProjectConfig {
  title: string;
  description: string;
  theme: string;
  duration: number;
  audience: string;
  platform: string;
  language: string;
  sceneCount: number;
  aspectRatio: string;
  visualStyle: string;
  autoGenerate: boolean;
  musicProvider: string;
  videoProvider: string;
  imageProvider: string;
}

const defaultConfig: ProjectConfig = {
  title: '',
  description: '',
  theme: '',
  duration: 60,
  audience: 'general',
  platform: 'YouTube',
  language: 'English',
  sceneCount: 4,
  aspectRatio: '16:9',
  visualStyle: 'cinematic',
  autoGenerate: true,
  musicProvider: 'suno',
  videoProvider: 'kling',
  imageProvider: 'flux',
};

export default function CreateProject() {
  const navigate = useNavigate();
  const [step, setStep] = useState<Step>('basic');
  const [config, setConfig] = useState<ProjectConfig>(defaultConfig);
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState('');

  const steps: { key: Step; label: string; icon: React.ReactNode }[] = [
    { key: 'basic', label: 'Basic Info', icon: <Sparkles className="h-4 w-4" /> },
    { key: 'concept', label: 'Concept', icon: <Wand2 className="h-4 w-4" /> },
    { key: 'options', label: 'Options', icon: <Video className="h-4 w-4" /> },
    { key: 'review', label: 'Review', icon: <Check className="h-4 w-4" /> },
  ];

  const currentStepIndex = steps.findIndex((s) => s.key === step);

  const updateConfig = (key: keyof ProjectConfig, value: string | number | boolean) => {
    setConfig((prev) => ({ ...prev, [key]: value }));
  };

  const canProceed = () => {
    switch (step) {
      case 'basic':
        return config.title.trim().length > 0;
      case 'concept':
        return config.theme.trim().length > 0 || config.title.trim().length > 0;
      case 'options':
        return true;
      case 'review':
        return true;
      default:
        return false;
    }
  };

  const nextStep = () => {
    const nextIndex = currentStepIndex + 1;
    if (nextIndex < steps.length) {
      setStep(steps[nextIndex].key);
    }
  };

  const prevStep = () => {
    const prevIndex = currentStepIndex - 1;
    if (prevIndex >= 0) {
      setStep(steps[prevIndex].key);
    }
  };

  const handleCreate = async () => {
    setIsCreating(true);
    setError('');

    try {
      // Create project
      const projectRes = await api.projects.create({
        title: config.title,
        description: config.description,
      });

      const projectId = projectRes.project.id;

      if (config.autoGenerate) {
        // Start full generation workflow
        await api.projects.generateAll(projectId, {
          theme: config.theme || config.title,
          duration: config.duration,
          audience: config.audience,
          platform: config.platform,
          language: config.language,
          scene_count: config.sceneCount,
          aspect_ratio: config.aspectRatio,
          visual_style: config.visualStyle,
          music_provider: config.musicProvider,
          video_provider: config.videoProvider,
          image_provider: config.imageProvider,
          auto_compose: true,
        });
      }

      navigate(`/projects/${projectId}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create project');
    } finally {
      setIsCreating(false);
    }
  };

  return (
    <DashboardLayout>
      <div className="max-w-3xl mx-auto">
        <div className="mb-8">
          <h1 className="text-2xl font-bold">Create New Project</h1>
          <p className="text-muted-foreground mt-1">
            Generate an AI-powered music video in minutes
          </p>
        </div>

        {/* Progress Steps */}
        <div className="mb-8">
          <div className="flex items-center justify-between">
            {steps.map((s, index) => (
              <div key={s.key} className="flex items-center">
                <button
                  onClick={() => index <= currentStepIndex && setStep(s.key)}
                  disabled={index > currentStepIndex}
                  className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-colors ${
                    step === s.key
                      ? 'bg-primary text-primary-foreground'
                      : index < currentStepIndex
                      ? 'bg-primary/20 text-primary hover:bg-primary/30'
                      : 'bg-muted text-muted-foreground'
                  }`}
                >
                  {s.icon}
                  <span className="hidden sm:inline">{s.label}</span>
                </button>
                {index < steps.length - 1 && (
                  <ChevronRight className="h-4 w-4 mx-2 text-muted-foreground" />
                )}
              </div>
            ))}
          </div>
        </div>

        {error && (
          <div className="mb-6 p-4 bg-destructive/10 border border-destructive/20 rounded-lg text-destructive">
            {error}
          </div>
        )}

        {/* Step Content */}
        <Card>
          <CardHeader>
            <CardTitle>{steps[currentStepIndex].label}</CardTitle>
            <CardDescription>
              {step === 'basic' && 'Enter basic information about your project'}
              {step === 'concept' && 'Define the theme and concept for AI generation'}
              {step === 'options' && 'Configure generation options'}
              {step === 'review' && 'Review your settings before creating'}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Basic Info Step */}
            {step === 'basic' && (
              <>
                <div className="space-y-2">
                  <Label htmlFor="title">Project Title *</Label>
                  <Input
                    id="title"
                    placeholder="My Awesome Music Video"
                    value={config.title}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => updateConfig('title', e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="description">Description (optional)</Label>
                  <Textarea
                    id="description"
                    placeholder="A brief description of your project..."
                    value={config.description}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => updateConfig('description', e.target.value)}
                    rows={3}
                  />
                </div>
              </>
            )}

            {/* Concept Step */}
            {step === 'concept' && (
              <>
                <div className="space-y-2">
                  <Label htmlFor="theme">Theme / Topic *</Label>
                  <Textarea
                    id="theme"
                    placeholder="e.g., A dreamy journey through neon-lit city streets at midnight, with themes of hope and new beginnings..."
                    value={config.theme}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => updateConfig('theme', e.target.value)}
                    rows={4}
                  />
                  <p className="text-xs text-muted-foreground">
                    Describe the mood, setting, and story for your music video
                  </p>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Target Audience</Label>
                    <Select
                      value={config.audience}
                      onValueChange={(v) => updateConfig('audience', v)}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="general">General</SelectItem>
                        <SelectItem value="kids">Kids</SelectItem>
                        <SelectItem value="teens">Teens</SelectItem>
                        <SelectItem value="adults">Adults</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>Platform</Label>
                    <Select
                      value={config.platform}
                      onValueChange={(v) => updateConfig('platform', v)}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="YouTube">YouTube</SelectItem>
                        <SelectItem value="TikTok">TikTok</SelectItem>
                        <SelectItem value="Instagram">Instagram</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="space-y-2">
                  <Label>Language</Label>
                  <Select
                    value={config.language}
                    onValueChange={(v) => updateConfig('language', v)}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="English">English</SelectItem>
                      <SelectItem value="Thai">Thai</SelectItem>
                      <SelectItem value="Japanese">Japanese</SelectItem>
                      <SelectItem value="Korean">Korean</SelectItem>
                      <SelectItem value="Chinese">Chinese</SelectItem>
                      <SelectItem value="Spanish">Spanish</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </>
            )}

            {/* Options Step */}
            {step === 'options' && (
              <>
                <div className="space-y-4">
                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <Label>Duration: {config.duration}s</Label>
                      <span className="text-sm text-muted-foreground">
                        {Math.floor(config.duration / 60)}:{(config.duration % 60).toString().padStart(2, '0')}
                      </span>
                    </div>
                    <Slider
                      value={[config.duration]}
                      onValueChange={([v]) => updateConfig('duration', v)}
                      min={15}
                      max={180}
                      step={15}
                    />
                  </div>

                  <div className="space-y-2">
                    <div className="flex justify-between">
                      <Label>Number of Scenes: {config.sceneCount}</Label>
                    </div>
                    <Slider
                      value={[config.sceneCount]}
                      onValueChange={([v]) => updateConfig('sceneCount', v)}
                      min={2}
                      max={10}
                      step={1}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Aspect Ratio</Label>
                    <Select
                      value={config.aspectRatio}
                      onValueChange={(v) => updateConfig('aspectRatio', v)}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="16:9">16:9 (YouTube)</SelectItem>
                        <SelectItem value="9:16">9:16 (TikTok/Reels)</SelectItem>
                        <SelectItem value="1:1">1:1 (Square)</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>Visual Style</Label>
                    <Select
                      value={config.visualStyle}
                      onValueChange={(v) => updateConfig('visualStyle', v)}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="cinematic">Cinematic</SelectItem>
                        <SelectItem value="anime">Anime</SelectItem>
                        <SelectItem value="realistic">Realistic</SelectItem>
                        <SelectItem value="3d">3D Render</SelectItem>
                        <SelectItem value="watercolor">Watercolor</SelectItem>
                        <SelectItem value="cyberpunk">Cyberpunk</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="space-y-4 pt-4 border-t">
                  <h4 className="font-medium flex items-center gap-2">
                    <Sparkles className="h-4 w-4" />
                    AI Providers
                  </h4>
                  <div className="grid grid-cols-3 gap-4">
                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Music className="h-4 w-4" /> Music
                      </Label>
                      <Select
                        value={config.musicProvider}
                        onValueChange={(v) => updateConfig('musicProvider', v)}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="suno">Suno</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Image className="h-4 w-4" /> Images
                      </Label>
                      <Select
                        value={config.imageProvider}
                        onValueChange={(v) => updateConfig('imageProvider', v)}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="flux">Flux</SelectItem>
                          <SelectItem value="midjourney">Midjourney</SelectItem>
                          <SelectItem value="dalle">DALL-E</SelectItem>
                          <SelectItem value="ideogram">Ideogram</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label className="flex items-center gap-2">
                        <Video className="h-4 w-4" /> Video
                      </Label>
                      <Select
                        value={config.videoProvider}
                        onValueChange={(v) => updateConfig('videoProvider', v)}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="kling">Kling</SelectItem>
                          <SelectItem value="hailuo">Hailuo</SelectItem>
                          <SelectItem value="runway">Runway</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                </div>

                <div className="flex items-center justify-between p-4 bg-muted rounded-lg">
                  <div>
                    <Label htmlFor="autoGenerate" className="font-medium">
                      Auto-Generate Everything
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Automatically generate music, images, and video
                    </p>
                  </div>
                  <Switch
                    id="autoGenerate"
                    checked={config.autoGenerate}
                    onCheckedChange={(v) => updateConfig('autoGenerate', v)}
                  />
                </div>
              </>
            )}

            {/* Review Step */}
            {step === 'review' && (
              <div className="space-y-6">
                <div className="grid gap-4">
                  <div className="p-4 bg-muted rounded-lg">
                    <h4 className="font-medium mb-2">Project Details</h4>
                    <dl className="space-y-1 text-sm">
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Title</dt>
                        <dd>{config.title}</dd>
                      </div>
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Theme</dt>
                        <dd className="max-w-xs truncate">{config.theme || config.title}</dd>
                      </div>
                    </dl>
                  </div>

                  <div className="p-4 bg-muted rounded-lg">
                    <h4 className="font-medium mb-2">Generation Settings</h4>
                    <dl className="space-y-1 text-sm">
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Duration</dt>
                        <dd>{config.duration}s</dd>
                      </div>
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Scenes</dt>
                        <dd>{config.sceneCount}</dd>
                      </div>
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Aspect Ratio</dt>
                        <dd>{config.aspectRatio}</dd>
                      </div>
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Style</dt>
                        <dd className="capitalize">{config.visualStyle}</dd>
                      </div>
                    </dl>
                  </div>

                  <div className="p-4 bg-muted rounded-lg">
                    <h4 className="font-medium mb-2">AI Providers</h4>
                    <dl className="space-y-1 text-sm">
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Music</dt>
                        <dd className="capitalize">{config.musicProvider}</dd>
                      </div>
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Images</dt>
                        <dd className="capitalize">{config.imageProvider}</dd>
                      </div>
                      <div className="flex justify-between">
                        <dt className="text-muted-foreground">Video</dt>
                        <dd className="capitalize">{config.videoProvider}</dd>
                      </div>
                    </dl>
                  </div>
                </div>

                {config.autoGenerate && (
                  <div className="p-4 bg-primary/10 border border-primary/20 rounded-lg">
                    <h4 className="font-medium text-primary mb-2 flex items-center gap-2">
                      <Sparkles className="h-4 w-4" />
                      Auto-Generation Enabled
                    </h4>
                    <p className="text-sm text-muted-foreground">
                      After creating the project, AI will automatically generate concept, music,
                      images, videos, and compose the final video.
                    </p>
                  </div>
                )}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Navigation Buttons */}
        <div className="flex justify-between mt-6">
          <Button variant="outline" onClick={prevStep} disabled={currentStepIndex === 0}>
            <ChevronLeft className="h-4 w-4 mr-2" />
            Back
          </Button>

          {step === 'review' ? (
            <Button onClick={handleCreate} disabled={isCreating}>
              {isCreating ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Creating...
                </>
              ) : (
                <>
                  <Wand2 className="h-4 w-4 mr-2" />
                  Create Project
                </>
              )}
            </Button>
          ) : (
            <Button onClick={nextStep} disabled={!canProceed()}>
              Next
              <ChevronRight className="h-4 w-4 ml-2" />
            </Button>
          )}
        </div>
      </div>
    </DashboardLayout>
  );
}
