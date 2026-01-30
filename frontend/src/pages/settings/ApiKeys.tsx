import { DashboardLayout } from '@/components/layout';
import { apiKeysApi, API_KEY_SERVICES } from '@/lib/api';
import type { ApiKeyItem, ApiKeyService } from '@/lib/api';
import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Bot,
  Palette,
  Loader2,
  CheckCircle2,
  XCircle,
  Eye,
  EyeOff,
  ExternalLink,
  Save,
  TestTube,
} from 'lucide-react';

interface ApiKeyCardProps {
  service: ApiKeyService;
  existingKey: ApiKeyItem | null;
  onSave: (service: ApiKeyService, key: string) => Promise<void>;
  onTest: (id: number) => Promise<void>;
  testResult: { success: boolean; message: string } | null;
  isTesting: boolean;
}

function ApiKeyCard({ service, existingKey, onSave, onTest, testResult, isTesting }: ApiKeyCardProps) {
  const config = API_KEY_SERVICES[service];
  const [keyValue, setKeyValue] = useState('');
  const [showKey, setShowKey] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState('');
  const [saveSuccess, setSaveSuccess] = useState(false);

  const isConnected = existingKey && existingKey.is_active;
  const hasChanges = keyValue.length > 0;

  const handleSave = async () => {
    if (!keyValue.trim()) return;
    setIsSaving(true);
    setSaveError('');
    setSaveSuccess(false);

    try {
      await onSave(service, keyValue);
      setKeyValue('');
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 5000);
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : 'Failed to save');
    } finally {
      setIsSaving(false);
    }
  };

  const ServiceIcon = service === 'openrouter' ? Bot : Palette;

  return (
    <Card>
      <CardHeader className="pb-4">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3">
            <div className={`p-2.5 rounded-lg ${service === 'openrouter' ? 'bg-blue-500/10' : 'bg-purple-500/10'}`}>
              <ServiceIcon className={`h-5 w-5 ${service === 'openrouter' ? 'text-blue-500' : 'text-purple-500'}`} />
            </div>
            <div>
              <CardTitle className="text-lg">{config.label}</CardTitle>
              <CardDescription className="mt-0.5">{config.description}</CardDescription>
            </div>
          </div>
          <div className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${
            isConnected
              ? 'bg-green-500/10 text-green-600 dark:text-green-400'
              : 'bg-muted text-muted-foreground'
          }`}>
            <span className={`w-1.5 h-1.5 rounded-full ${isConnected ? 'bg-green-500' : 'bg-muted-foreground'}`} />
            {isConnected ? (existingKey?.credits_remaining ? `Connected (${existingKey.credits_remaining.toLocaleString()})` : 'Connected') : 'Not Set'}
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <p className="text-sm text-muted-foreground">
          <span className="font-medium">Used for:</span> {config.usedFor}
        </p>

        {/* API Key Input */}
        <div className="space-y-2">
          <div className="relative">
            <Input
              type={showKey ? 'text' : 'password'}
              value={keyValue}
              onChange={(e) => setKeyValue(e.target.value)}
              placeholder={existingKey ? existingKey.key_masked : `Enter your ${config.label} API key`}
              className="pr-10 font-mono text-sm"
            />
            <button
              type="button"
              onClick={() => setShowKey(!showKey)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
            >
              {showKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>

        {/* Credits display for kie.ai */}
        {service === 'kie' && existingKey && existingKey.credits_remaining !== null && (
          <p className="text-sm">
            <span className="text-muted-foreground">Credits:</span>{' '}
            <span className="font-medium">{existingKey.credits_remaining.toLocaleString()} remaining</span>
          </p>
        )}

        {/* Get API key link */}
        <a
          href={config.getKeyUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
        >
          Get your API key at {config.getKeyUrl.replace('https://', '')}
          <ExternalLink className="h-3.5 w-3.5" />
        </a>

        {/* Error/Success messages */}
        {saveError && (
          <div className="flex items-center gap-2 text-sm text-destructive">
            <XCircle className="h-4 w-4" />
            {saveError}
          </div>
        )}
        {saveSuccess && (
          <div className="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
            <CheckCircle2 className="h-4 w-4" />
            API key saved successfully
          </div>
        )}
        {testResult && (
          <div className={`flex items-center gap-2 text-sm ${testResult.success ? 'text-green-600 dark:text-green-400' : 'text-destructive'}`}>
            {testResult.success ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
            {testResult.message}
          </div>
        )}

        {/* Actions */}
        <div className="flex items-center gap-2 pt-2">
          {existingKey && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => onTest(existingKey.id)}
              disabled={isTesting}
            >
              {isTesting ? (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              ) : (
                <TestTube className="h-4 w-4 mr-2" />
              )}
              Test Connection
            </Button>
          )}
          <Button
            size="sm"
            onClick={handleSave}
            disabled={!hasChanges || isSaving}
            className="ml-auto"
          >
            {isSaving ? (
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
            ) : (
              <Save className="h-4 w-4 mr-2" />
            )}
            Save
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

export default function ApiKeys() {
  const [apiKeys, setApiKeys] = useState<ApiKeyItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [testingId, setTestingId] = useState<number | null>(null);
  const [testResults, setTestResults] = useState<Record<number, { success: boolean; message: string }>>({});

  useEffect(() => {
    loadApiKeys();
  }, []);

  async function loadApiKeys() {
    try {
      const response = await apiKeysApi.list();
      setApiKeys(response.api_keys);
    } catch (error) {
      console.error('Failed to load API keys:', error);
    } finally {
      setIsLoading(false);
    }
  }

  const getKeyForService = (service: ApiKeyService): ApiKeyItem | null => {
    return apiKeys.find(key => key.service === service) || null;
  };

  const handleSave = useCallback(async (service: ApiKeyService, keyValue: string) => {
    const existing = getKeyForService(service);

    if (existing) {
      // Update existing key
      const response = await apiKeysApi.update(existing.id, { key: keyValue });
      setApiKeys(prev => prev.map(k => k.id === existing.id ? response.api_key : k));
    } else {
      // Create new key
      const response = await apiKeysApi.create({
        service,
        name: API_KEY_SERVICES[service].label,
        key: keyValue,
      });
      setApiKeys(prev => [...prev, response.api_key]);
    }
  }, [apiKeys]);

  const handleTest = useCallback(async (id: number) => {
    setTestingId(id);
    setTestResults(prev => ({ ...prev, [id]: { success: false, message: 'Testing...' } }));

    try {
      const result = await apiKeysApi.test(id);
      setTestResults(prev => ({
        ...prev,
        [id]: { success: result.success, message: result.message },
      }));

      if (result.success && result.credits !== undefined) {
        setApiKeys(prev =>
          prev.map(key =>
            key.id === id ? { ...key, credits_remaining: result.credits! } : key
          )
        );
      }
    } catch (error: unknown) {
      // Extract error message from API response
      let errorMessage = 'Test failed';
      if (error && typeof error === 'object' && 'message' in error) {
        errorMessage = (error as { message: string }).message;
      }
      setTestResults(prev => ({
        ...prev,
        [id]: { success: false, message: errorMessage },
      }));
    } finally {
      setTestingId(null);
    }
  }, []);

  const services: ApiKeyService[] = ['openrouter', 'kie'];

  return (
    <DashboardLayout>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-foreground">API Keys</h1>
        <p className="text-muted-foreground mt-1">
          Connect your API keys to enable AI features
        </p>
      </div>

      {/* API Key Cards */}
      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="animate-spin text-muted-foreground" size={32} />
        </div>
      ) : (
        <div className="space-y-4">
          {services.map(service => {
            const existingKey = getKeyForService(service);
            return (
              <ApiKeyCard
                key={service}
                service={service}
                existingKey={existingKey}
                onSave={handleSave}
                onTest={handleTest}
                testResult={existingKey ? testResults[existingKey.id] || null : null}
                isTesting={existingKey ? testingId === existingKey.id : false}
              />
            );
          })}
        </div>
      )}
    </DashboardLayout>
  );
}
