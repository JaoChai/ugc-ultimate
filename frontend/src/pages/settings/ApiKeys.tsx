import { DashboardLayout } from '@/components/layout';
import { apiKeysApi } from '@/lib/api';
import type { ApiKeyItem } from '@/lib/api';
import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Plus,
  Key,
  Trash2,
  TestTube,
  Loader2,
  CheckCircle2,
  XCircle,
  Eye,
  EyeOff,
} from 'lucide-react';

export default function ApiKeys() {
  const [apiKeys, setApiKeys] = useState<ApiKeyItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [testingId, setTestingId] = useState<number | null>(null);
  const [testResults, setTestResults] = useState<Record<number, { success: boolean; message: string }>>({});

  // Form state
  const [formService, setFormService] = useState<'kie' | 'r2'>('kie');
  const [formName, setFormName] = useState('');
  const [formKey, setFormKey] = useState('');
  const [showKey, setShowKey] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState('');

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

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setFormError('');
    setIsSubmitting(true);

    try {
      const response = await apiKeysApi.create({
        service: formService,
        name: formName,
        key: formKey,
      });
      setApiKeys([response.api_key, ...apiKeys]);
      setShowModal(false);
      resetForm();
    } catch (error) {
      setFormError('Failed to add API key. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleTest(id: number) {
    setTestingId(id);
    setTestResults((prev) => ({ ...prev, [id]: { success: false, message: 'Testing...' } }));

    try {
      const result = await apiKeysApi.test(id);
      setTestResults((prev) => ({
        ...prev,
        [id]: { success: result.success, message: result.message },
      }));

      if (result.success && result.credits !== undefined) {
        setApiKeys((prev) =>
          prev.map((key) =>
            key.id === id ? { ...key, credits_remaining: result.credits! } : key
          )
        );
      }
    } catch (error) {
      setTestResults((prev) => ({
        ...prev,
        [id]: { success: false, message: 'Test failed' },
      }));
    } finally {
      setTestingId(null);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('Are you sure you want to delete this API key?')) return;

    try {
      await apiKeysApi.delete(id);
      setApiKeys((prev) => prev.filter((key) => key.id !== id));
    } catch (error) {
      console.error('Failed to delete API key:', error);
    }
  }

  function resetForm() {
    setFormService('kie');
    setFormName('');
    setFormKey('');
    setShowKey(false);
    setFormError('');
  }

  return (
    <DashboardLayout>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">API Keys</h1>
          <p className="text-muted-foreground mt-1">
            Manage your API keys for kie.ai and Cloudflare R2
          </p>
        </div>
        <Button onClick={() => setShowModal(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Add API Key
        </Button>
      </div>

      {/* API Keys list */}
      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="animate-spin text-muted-foreground" size={32} />
        </div>
      ) : apiKeys.length === 0 ? (
        <Card className="p-12 text-center">
          <Key className="mx-auto text-muted-foreground/50 h-16 w-16" />
          <h3 className="text-lg font-semibold text-foreground mt-4">No API keys yet</h3>
          <p className="text-muted-foreground mt-2 max-w-sm mx-auto">
            Add your kie.ai API key to start generating music and videos
          </p>
          <Button onClick={() => setShowModal(true)} className="mt-6">
            <Plus className="h-4 w-4 mr-2" />
            Add First API Key
          </Button>
        </Card>
      ) : (
        <div className="space-y-4">
          {apiKeys.map((apiKey) => (
            <Card key={apiKey.id}>
              <CardContent className="pt-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div className="flex items-start gap-4">
                    <div
                      className={`p-3 rounded-lg ${
                        apiKey.service === 'kie' ? 'bg-primary/10' : 'bg-amber-500/10'
                      }`}
                    >
                      <Key
                        className={apiKey.service === 'kie' ? 'text-primary' : 'text-amber-500'}
                        size={24}
                      />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <h3 className="font-semibold text-foreground">{apiKey.name}</h3>
                        <span
                          className={`text-xs font-medium px-2 py-0.5 rounded ${
                            apiKey.is_active
                              ? 'bg-green-500/10 text-green-500'
                              : 'bg-secondary text-muted-foreground'
                          }`}
                        >
                          {apiKey.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </div>
                      <p className="text-sm text-muted-foreground mt-1">
                        {apiKey.service === 'kie' ? 'kie.ai' : 'Cloudflare R2'} • {apiKey.key_masked}
                      </p>
                      {apiKey.service === 'kie' && (
                        <p className="text-sm mt-2">
                          <span className="text-muted-foreground">Credits:</span>{' '}
                          <span className="font-medium text-foreground">
                            {apiKey.credits_remaining || '-'}
                          </span>
                        </p>
                      )}
                      {testResults[apiKey.id] && (
                        <div
                          className={`flex items-center gap-1.5 mt-2 text-sm ${
                            testResults[apiKey.id].success ? 'text-green-500' : 'text-destructive'
                          }`}
                        >
                          {testResults[apiKey.id].success ? (
                            <CheckCircle2 size={14} />
                          ) : (
                            <XCircle size={14} />
                          )}
                          {testResults[apiKey.id].message}
                        </div>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleTest(apiKey.id)}
                      disabled={testingId === apiKey.id}
                    >
                      {testingId === apiKey.id ? (
                        <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                      ) : (
                        <TestTube className="h-4 w-4 mr-2" />
                      )}
                      Test
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleDelete(apiKey.id)}
                      className="text-muted-foreground hover:text-destructive"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Add API Key Modal */}
      <Dialog open={showModal} onOpenChange={(open) => {
        setShowModal(open);
        if (!open) resetForm();
      }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add API Key</DialogTitle>
            <DialogDescription>
              Add a new API key to connect with external services
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            {formError && (
              <div className="p-3 bg-destructive/10 text-destructive text-sm rounded-lg">
                {formError}
              </div>
            )}

            <div className="space-y-2">
              <Label>Service</Label>
              <Select value={formService} onValueChange={(v: 'kie' | 'r2') => setFormService(v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="kie">kie.ai (Music & Video Generation)</SelectItem>
                  <SelectItem value="r2">Cloudflare R2 (Storage)</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="apiKeyName">Name</Label>
              <Input
                id="apiKeyName"
                value={formName}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFormName(e.target.value)}
                placeholder="My API Key"
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="apiKeyValue">API Key</Label>
              <div className="relative">
                <Input
                  id="apiKeyValue"
                  type={showKey ? 'text' : 'password'}
                  value={formKey}
                  onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFormKey(e.target.value)}
                  placeholder="Enter your API key"
                  required
                  className="pr-10"
                />
                <button
                  type="button"
                  onClick={() => setShowKey(!showKey)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                  {showKey ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setShowModal(false);
                  resetForm();
                }}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={isSubmitting || !formName || !formKey}>
                {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                Add Key
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
