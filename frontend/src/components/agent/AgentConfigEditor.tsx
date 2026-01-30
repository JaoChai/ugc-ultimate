import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Slider } from '@/components/ui/slider';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { ModelSelector } from './ModelSelector';
import { agentConfigsApi, type AgentConfig, AGENT_TYPE_LABELS, type AgentType } from '@/lib/api';
import { Save, RotateCcw, Play, Loader2, Star, StarOff } from 'lucide-react';

interface AgentConfigEditorProps {
  agentType: AgentType;
  config?: AgentConfig | null;
  onSave?: (config: AgentConfig) => void;
  onTest?: (config: AgentConfig) => void;
}

interface ConfigFormData {
  name: string;
  system_prompt: string;
  model: string;
  temperature: number;
  max_tokens: number;
}

export function AgentConfigEditor({ agentType, config, onSave, onTest }: AgentConfigEditorProps) {
  const [formData, setFormData] = useState<ConfigFormData>({
    name: 'Default',
    system_prompt: '',
    model: 'google/gemini-2.0-flash-exp',
    temperature: 0.7,
    max_tokens: 2000,
  });
  const [loading, setLoading] = useState(false);
  const [testLoading, setTestLoading] = useState(false);
  const [resetLoading, setResetLoading] = useState(false);
  const [defaultLoading, setDefaultLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Load config or fetch default prompt
  useEffect(() => {
    if (config) {
      setFormData({
        name: config.name,
        system_prompt: config.system_prompt,
        model: config.model || 'google/gemini-2.0-flash-exp',
        temperature: config.parameters?.temperature ?? 0.7,
        max_tokens: config.parameters?.max_tokens ?? 2000,
      });
    } else {
      // Fetch default prompt for this agent type
      fetchDefaultPrompt();
    }
  }, [config, agentType]);

  const fetchDefaultPrompt = async () => {
    try {
      const data = await agentConfigsApi.getDefaultPrompt(agentType);
      setFormData((prev) => ({
        ...prev,
        system_prompt: data.default_prompt,
        model: data.default_model || 'google/gemini-2.0-flash-exp',
        temperature: data.default_parameters?.temperature ?? 0.7,
        max_tokens: data.default_parameters?.max_tokens ?? 2000,
      }));
    } catch (err) {
      console.error('Failed to fetch default prompt:', err);
    }
  };

  const handleSave = async () => {
    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const payload = {
        agent_type: agentType,
        name: formData.name,
        system_prompt: formData.system_prompt,
        model: formData.model,
        parameters: {
          temperature: formData.temperature,
          max_tokens: formData.max_tokens,
        },
      };

      let savedConfig: AgentConfig;
      if (config && config.id > 0) {
        const response = await agentConfigsApi.update(config.id, payload);
        savedConfig = response.config;
      } else {
        const response = await agentConfigsApi.create(payload);
        savedConfig = response.config;
      }

      setSuccess('Configuration saved successfully');
      onSave?.(savedConfig);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save configuration');
    } finally {
      setLoading(false);
    }
  };

  const handleReset = async () => {
    if (!config || config.id === 0) return;

    setResetLoading(true);
    setError('');
    setSuccess('');

    try {
      const response = await agentConfigsApi.resetToDefault(config.id);
      const resetConfig = response.config;
      setFormData({
        name: resetConfig.name,
        system_prompt: resetConfig.system_prompt,
        model: resetConfig.model || 'google/gemini-2.0-flash-exp',
        temperature: resetConfig.parameters?.temperature ?? 0.7,
        max_tokens: resetConfig.parameters?.max_tokens ?? 2000,
      });
      setSuccess('Reset to default prompt');
      onSave?.(resetConfig);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to reset');
    } finally {
      setResetLoading(false);
    }
  };

  const handleSetDefault = async () => {
    if (!config || config.id === 0) return;

    setDefaultLoading(true);
    setError('');
    setSuccess('');

    try {
      const response = await agentConfigsApi.setDefault(config.id);
      setSuccess('Set as default configuration');
      onSave?.(response.config);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to set as default');
    } finally {
      setDefaultLoading(false);
    }
  };

  const handleTest = async () => {
    if (!config || config.id === 0) return;

    setTestLoading(true);
    setError('');
    setSuccess('');

    try {
      const result = await agentConfigsApi.test(config.id, 'Test the AI agent configuration.');
      setSuccess(`Test successful! Config: ${result.config.name}`);
      onTest?.(config);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Test failed');
    } finally {
      setTestLoading(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-lg">{AGENT_TYPE_LABELS[agentType]}</CardTitle>
            <CardDescription>Configure the system prompt and model settings</CardDescription>
          </div>
          {config?.is_default && (
            <span className="flex items-center gap-1 text-xs text-yellow-500">
              <Star className="h-4 w-4 fill-current" />
              Default
            </span>
          )}
        </div>
      </CardHeader>

      <CardContent className="space-y-6">
        {/* Name */}
        <div className="space-y-2">
          <Label htmlFor="name">Configuration Name</Label>
          <Input
            id="name"
            value={formData.name}
            onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
            placeholder="e.g. Creative, Conservative, etc."
          />
        </div>

        {/* Model */}
        <ModelSelector
          value={formData.model}
          onChange={(model) => setFormData((prev) => ({ ...prev, model }))}
          description="Enter any OpenRouter model ID"
        />

        {/* System Prompt */}
        <div className="space-y-2">
          <Label htmlFor="system_prompt">System Prompt</Label>
          <Textarea
            id="system_prompt"
            value={formData.system_prompt}
            onChange={(e) => setFormData((prev) => ({ ...prev, system_prompt: e.target.value }))}
            placeholder="Enter the system prompt for this agent..."
            rows={10}
            className="font-mono text-sm"
          />
          <p className="text-xs text-muted-foreground">
            The system prompt defines how the AI agent behaves and what it outputs.
          </p>
        </div>

        {/* Parameters */}
        <div className="grid grid-cols-2 gap-6">
          {/* Temperature */}
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <Label>Temperature</Label>
              <span className="text-sm text-muted-foreground">{formData.temperature.toFixed(1)}</span>
            </div>
            <Slider
              value={[formData.temperature]}
              onValueChange={([value]) => setFormData((prev) => ({ ...prev, temperature: value }))}
              min={0}
              max={2}
              step={0.1}
            />
            <p className="text-xs text-muted-foreground">
              Lower = more focused, Higher = more creative
            </p>
          </div>

          {/* Max Tokens */}
          <div className="space-y-2">
            <Label htmlFor="max_tokens">Max Tokens</Label>
            <Input
              id="max_tokens"
              type="number"
              value={formData.max_tokens}
              onChange={(e) => setFormData((prev) => ({ ...prev, max_tokens: parseInt(e.target.value) || 2000 }))}
              min={100}
              max={8000}
            />
            <p className="text-xs text-muted-foreground">
              Maximum length of the response
            </p>
          </div>
        </div>

        {/* Error/Success Messages */}
        {error && (
          <div className="p-3 bg-destructive/10 border border-destructive/20 rounded-lg text-sm text-destructive">
            {error}
          </div>
        )}
        {success && (
          <div className="p-3 bg-green-500/10 border border-green-500/20 rounded-lg text-sm text-green-500">
            {success}
          </div>
        )}
      </CardContent>

      <CardFooter className="flex justify-between">
        <div className="flex gap-2">
          {config && config.id > 0 && (
            <>
              <Button variant="outline" onClick={handleReset} disabled={resetLoading}>
                {resetLoading ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <RotateCcw className="h-4 w-4 mr-2" />
                )}
                Reset
              </Button>
              <Button variant="outline" onClick={handleTest} disabled={testLoading}>
                {testLoading ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <Play className="h-4 w-4 mr-2" />
                )}
                Test
              </Button>
              {!config.is_default && (
                <Button variant="outline" onClick={handleSetDefault} disabled={defaultLoading}>
                  {defaultLoading ? (
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  ) : (
                    <StarOff className="h-4 w-4 mr-2" />
                  )}
                  Set Default
                </Button>
              )}
            </>
          )}
        </div>
        <Button onClick={handleSave} disabled={loading}>
          {loading ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
          Save
        </Button>
      </CardFooter>
    </Card>
  );
}
