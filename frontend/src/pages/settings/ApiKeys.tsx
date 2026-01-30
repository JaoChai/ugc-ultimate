import { DashboardLayout } from '@/components/layout';
import { apiKeysApi } from '@/lib/api';
import type { ApiKeyItem } from '@/lib/api';
import { useState, useEffect } from 'react';
import type { FormEvent } from 'react';
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
  X,
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
        <button
          onClick={() => setShowModal(true)}
          className="inline-flex items-center gap-2 bg-primary text-primary-foreground px-4 py-2 rounded-lg font-medium hover:bg-primary/90 transition-colors"
        >
          <Plus size={18} />
          Add API Key
        </button>
      </div>

      {/* API Keys list */}
      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="animate-spin text-muted-foreground" size={32} />
        </div>
      ) : apiKeys.length === 0 ? (
        <div className="bg-card rounded-xl border border-border p-12 text-center">
          <Key className="mx-auto text-muted-foreground/50" size={64} />
          <h3 className="text-lg font-semibold text-foreground mt-4">No API keys yet</h3>
          <p className="text-muted-foreground mt-2 max-w-sm mx-auto">
            Add your kie.ai API key to start generating music and videos
          </p>
          <button
            onClick={() => setShowModal(true)}
            className="inline-flex items-center gap-2 bg-primary text-primary-foreground px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-colors mt-6"
          >
            <Plus size={18} />
            Add First API Key
          </button>
        </div>
      ) : (
        <div className="space-y-4">
          {apiKeys.map((apiKey) => (
            <div
              key={apiKey.id}
              className="bg-card rounded-xl border border-border p-6"
            >
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
                  <button
                    onClick={() => handleTest(apiKey.id)}
                    disabled={testingId === apiKey.id}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border text-sm text-foreground hover:bg-secondary transition-colors disabled:opacity-50"
                  >
                    {testingId === apiKey.id ? (
                      <Loader2 size={14} className="animate-spin" />
                    ) : (
                      <TestTube size={14} />
                    )}
                    Test
                  </button>
                  <button
                    onClick={() => handleDelete(apiKey.id)}
                    className="p-2 rounded-lg text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Add API Key Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-background/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-card rounded-xl border border-border shadow-lg w-full max-w-md">
            <div className="flex items-center justify-between px-6 py-4 border-b border-border">
              <h2 className="text-lg font-semibold text-foreground">Add API Key</h2>
              <button
                onClick={() => {
                  setShowModal(false);
                  resetForm();
                }}
                className="p-2 rounded-lg hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors"
              >
                <X size={18} />
              </button>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              {formError && (
                <div className="bg-destructive/10 text-destructive px-4 py-3 rounded-lg text-sm">
                  {formError}
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Service</label>
                <select
                  value={formService}
                  onChange={(e) => setFormService(e.target.value as 'kie' | 'r2')}
                  className="w-full px-3 py-2 bg-background border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                >
                  <option value="kie">kie.ai (Music & Video Generation)</option>
                  <option value="r2">Cloudflare R2 (Storage)</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Name</label>
                <input
                  type="text"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  placeholder="My API Key"
                  required
                  className="w-full px-3 py-2 bg-background border border-border rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">API Key</label>
                <div className="relative">
                  <input
                    type={showKey ? 'text' : 'password'}
                    value={formKey}
                    onChange={(e) => setFormKey(e.target.value)}
                    placeholder="Enter your API key"
                    required
                    className="w-full px-3 py-2 pr-10 bg-background border border-border rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
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

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    setShowModal(false);
                    resetForm();
                  }}
                  className="flex-1 px-4 py-2 border border-border rounded-lg text-foreground hover:bg-secondary transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="flex-1 bg-primary text-primary-foreground px-4 py-2 rounded-lg font-medium hover:bg-primary/90 transition-colors disabled:opacity-50"
                >
                  {isSubmitting ? 'Adding...' : 'Add Key'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
}
