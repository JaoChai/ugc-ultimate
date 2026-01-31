import { useState, useEffect, useCallback } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { AgentConfigEditor } from '@/components/agent/AgentConfigEditor';
import {
  agentConfigsApi,
  PIPELINE_STEPS,
  AGENT_TYPE_LABELS,
  MUSIC_VIDEO_AGENT_TYPES,
  MUSIC_VIDEO_AGENT_TYPE_LABELS,
  type AgentConfig,
  type AgentType,
  type MusicVideoAgentType,
} from '@/lib/api';
import { ArrowLeft, Loader2, Plus, Music, Video } from 'lucide-react';
import { Link } from 'react-router-dom';

type PipelineCategory = 'video' | 'music_video';

export default function AgentConfigPage() {
  const [configs, setConfigs] = useState<AgentConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [pipelineCategory, setPipelineCategory] = useState<PipelineCategory>('video');
  const [activeTab, setActiveTab] = useState<string>(PIPELINE_STEPS[0]);

  const fetchConfigs = useCallback(async () => {
    try {
      const data = await agentConfigsApi.list();
      setConfigs(data.configs);
    } catch (err) {
      console.error('Failed to fetch configs:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchConfigs();
  }, [fetchConfigs]);

  // Reset active tab when pipeline category changes
  useEffect(() => {
    if (pipelineCategory === 'video') {
      setActiveTab(PIPELINE_STEPS[0]);
    } else {
      setActiveTab(MUSIC_VIDEO_AGENT_TYPES[0]);
    }
  }, [pipelineCategory]);

  const getConfigsForType = (agentType: string) => {
    return configs.filter((c) => c.agent_type === agentType);
  };

  const getDefaultConfig = (agentType: string): AgentConfig | null => {
    const typeConfigs = getConfigsForType(agentType);
    return typeConfigs.find((c) => c.is_default) || typeConfigs[0] || null;
  };

  const handleSave = (savedConfig: AgentConfig) => {
    setConfigs((prev) => {
      const exists = prev.find((c) => c.id === savedConfig.id);
      if (exists) {
        // If setting as default, update other configs
        if (savedConfig.is_default) {
          return prev.map((c) =>
            c.id === savedConfig.id
              ? savedConfig
              : c.agent_type === savedConfig.agent_type
              ? { ...c, is_default: false }
              : c
          );
        }
        return prev.map((c) => (c.id === savedConfig.id ? savedConfig : c));
      }
      return [...prev, savedConfig];
    });
  };

  const getCurrentSteps = () => {
    return pipelineCategory === 'video' ? PIPELINE_STEPS : MUSIC_VIDEO_AGENT_TYPES;
  };

  const getLabel = (step: string) => {
    if (pipelineCategory === 'video') {
      return AGENT_TYPE_LABELS[step as AgentType];
    }
    return MUSIC_VIDEO_AGENT_TYPE_LABELS[step as MusicVideoAgentType];
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

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" asChild>
            <Link to="/settings">
              <ArrowLeft className="h-5 w-5" />
            </Link>
          </Button>
          <div>
            <h1 className="text-2xl font-bold">AI Agents Configuration</h1>
            <p className="text-muted-foreground mt-1">
              Customize system prompts and model settings for each pipeline agent
            </p>
          </div>
        </div>

        {/* Pipeline Category Switcher */}
        <div className="flex gap-2">
          <Button
            variant={pipelineCategory === 'video' ? 'default' : 'outline'}
            onClick={() => setPipelineCategory('video')}
            className="flex items-center gap-2"
          >
            <Video className="h-4 w-4" />
            Video Pipeline
          </Button>
          <Button
            variant={pipelineCategory === 'music_video' ? 'default' : 'outline'}
            onClick={() => setPipelineCategory('music_video')}
            className="flex items-center gap-2"
          >
            <Music className="h-4 w-4" />
            Music Video Pipeline
          </Button>
        </div>

        {/* Description based on pipeline type */}
        <div className="text-sm text-muted-foreground bg-muted/50 p-4 rounded-lg">
          {pipelineCategory === 'video' ? (
            <p>
              <strong>Video Pipeline:</strong> Theme Director → Music Composer → Visual Director → Image Generator → Video Composer
            </p>
          ) : (
            <p>
              <strong>Music Video Pipeline:</strong> Song Architect → Suno Expert → Song Selector → Visual Designer → FFmpeg Compose
            </p>
          )}
        </div>

        {/* Agent Type Tabs */}
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList className={`grid w-full ${pipelineCategory === 'video' ? 'grid-cols-5' : 'grid-cols-4'}`}>
            {getCurrentSteps().map((step) => (
              <TabsTrigger key={step} value={step} className="text-xs md:text-sm">
                {getLabel(step)?.replace(' ', '\n')}
              </TabsTrigger>
            ))}
          </TabsList>

          {getCurrentSteps().map((step) => (
            <TabsContent key={step} value={step} className="space-y-4 mt-4">
              {/* Config Editor */}
              <AgentConfigEditor
                agentType={step}
                config={getDefaultConfig(step)}
                onSave={handleSave}
              />

              {/* Other Configurations */}
              {getConfigsForType(step).length > 1 && (
                <div className="space-y-4">
                  <h3 className="text-sm font-medium text-muted-foreground">Other Configurations</h3>
                  <div className="grid gap-4">
                    {getConfigsForType(step)
                      .filter((c) => !c.is_default)
                      .map((config) => (
                        <AgentConfigEditor
                          key={config.id}
                          agentType={step}
                          config={config}
                          onSave={handleSave}
                        />
                      ))}
                  </div>
                </div>
              )}

              {/* Add New Config Button */}
              <Button variant="outline" className="w-full" onClick={() => {
                // Create a new empty config (will trigger create instead of update)
                const newConfig: AgentConfig = {
                  id: 0,
                  user_id: 0,
                  agent_type: step,
                  name: `New ${getLabel(step)} Config`,
                  system_prompt: '',
                  model: 'google/gemini-3-flash-preview',
                  parameters: { temperature: 0.7, max_tokens: 2000 },
                  is_default: false,
                  created_at: new Date().toISOString(),
                  updated_at: new Date().toISOString(),
                };
                setConfigs((prev) => [...prev, newConfig]);
              }}>
                <Plus className="h-4 w-4 mr-2" />
                Add New Configuration
              </Button>
            </TabsContent>
          ))}
        </Tabs>
      </div>
    </DashboardLayout>
  );
}
