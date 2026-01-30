import { useState, useEffect, useCallback } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { AgentConfigEditor } from '@/components/agent/AgentConfigEditor';
import { agentConfigsApi, PIPELINE_STEPS, AGENT_TYPE_LABELS, type AgentConfig, type AgentType } from '@/lib/api';
import { ArrowLeft, Loader2, Plus } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function AgentConfigPage() {
  const [configs, setConfigs] = useState<AgentConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<AgentType>(PIPELINE_STEPS[0]);

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

  const getConfigsForType = (agentType: AgentType) => {
    return configs.filter((c) => c.agent_type === agentType);
  };

  const getDefaultConfig = (agentType: AgentType): AgentConfig | null => {
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

        {/* Agent Type Tabs */}
        <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as AgentType)}>
          <TabsList className="grid grid-cols-5 w-full">
            {PIPELINE_STEPS.map((step) => (
              <TabsTrigger key={step} value={step} className="text-xs md:text-sm">
                {AGENT_TYPE_LABELS[step].replace(' ', '\n')}
              </TabsTrigger>
            ))}
          </TabsList>

          {PIPELINE_STEPS.map((step) => (
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
                  name: `New ${AGENT_TYPE_LABELS[step]} Config`,
                  system_prompt: '',
                  model: 'google/gemini-2.0-flash-exp',
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
