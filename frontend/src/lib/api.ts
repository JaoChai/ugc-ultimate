import { apiCache, createCacheKey, CACHE_TTL } from './apiCache';

// Ensure API URL always ends with /api
const getApiBaseUrl = () => {
  const envUrl = import.meta.env.VITE_API_URL;
  if (!envUrl) return '/api';
  // If URL doesn't end with /api, append it
  return envUrl.endsWith('/api') ? envUrl : `${envUrl}/api`;
};
const API_BASE_URL = getApiBaseUrl();

interface RequestOptions extends RequestInit {
  params?: Record<string, string>;
}

class ApiClient {
  private baseUrl: string;
  private token: string | null = null;

  constructor(baseUrl: string) {
    this.baseUrl = baseUrl;
    this.token = localStorage.getItem('auth_token');
  }

  setToken(token: string | null) {
    this.token = token;
    if (token) {
      localStorage.setItem('auth_token', token);
    } else {
      localStorage.removeItem('auth_token');
    }
  }

  getToken(): string | null {
    return this.token;
  }

  private async request<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
    const { params, ...fetchOptions } = options;

    let url = `${this.baseUrl}${endpoint}`;
    if (params) {
      const searchParams = new URLSearchParams(params);
      url += `?${searchParams.toString()}`;
    }

    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...options.headers,
    };

    if (this.token) {
      (headers as Record<string, string>)['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(url, {
      ...fetchOptions,
      headers,
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new ApiError(
        error.message || 'An error occurred',
        response.status,
        error.errors
      );
    }

    return response.json();
  }

  async get<T>(endpoint: string, params?: Record<string, string>): Promise<T> {
    return this.request<T>(endpoint, { method: 'GET', params });
  }

  /**
   * GET request with caching
   * @param endpoint - API endpoint
   * @param params - Query parameters
   * @param ttl - Cache TTL in milliseconds (optional)
   */
  async cachedGet<T>(endpoint: string, params?: Record<string, string>, ttl?: number): Promise<T> {
    const cacheKey = createCacheKey(endpoint, params);

    // Check cache first
    const cached = apiCache.get<T>(cacheKey);
    if (cached !== null) {
      return cached;
    }

    // Fetch from API
    const data = await this.get<T>(endpoint, params);

    // Cache the response
    apiCache.set(cacheKey, data, ttl);

    return data;
  }

  /**
   * Invalidate cache entries matching a pattern
   * Call this after mutations (POST, PUT, DELETE)
   */
  invalidateCache(pattern?: string): void {
    apiCache.invalidate(pattern);
  }

  async post<T>(endpoint: string, data?: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: data ? JSON.stringify(data) : undefined,
    });
  }

  async put<T>(endpoint: string, data?: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: data ? JSON.stringify(data) : undefined,
    });
  }

  async delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'DELETE' });
  }
}

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

const apiClient = new ApiClient(API_BASE_URL);

// Auth API
export const authApi = {
  register: (data: { name: string; email: string; password: string; password_confirmation: string }) =>
    apiClient.post<{ message: string; user: User; token: string }>('/auth/register', data),

  login: (data: { email: string; password: string }) =>
    apiClient.post<{ message: string; user: User; token: string }>('/auth/login', data),

  logout: () => apiClient.post<{ message: string }>('/auth/logout'),

  getUser: () => apiClient.get<{ user: User }>('/auth/user'),
};

// API Keys API
export const apiKeysApi = {
  list: () => apiClient.get<{ api_keys: ApiKeyItem[] }>('/api-keys'),

  create: (data: { service: string; name: string; key: string }) =>
    apiClient.post<{ message: string; api_key: ApiKeyItem }>('/api-keys', data),

  update: (id: number, data: { name?: string; key?: string; is_active?: boolean }) =>
    apiClient.put<{ message: string; api_key: ApiKeyItem }>(`/api-keys/${id}`, data),

  delete: (id: number) => apiClient.delete<{ message: string }>(`/api-keys/${id}`),

  test: (id: number) =>
    apiClient.post<{ success: boolean; message: string; credits?: number }>(`/api-keys/${id}/test`),
};

// Types
export interface User {
  id: number;
  name: string;
  email: string;
  created_at: string;
  updated_at: string;
}

export interface ApiKeyItem {
  id: number;
  service: ApiKeyService;
  name: string;
  key_masked: string;
  credits_remaining: number | null;
  is_active: boolean;
  last_used_at: string | null;
  created_at: string;
}

// API Key Service Types
export type ApiKeyService = 'openrouter' | 'kie';

export const API_KEY_SERVICES: Record<ApiKeyService, {
  label: string;
  description: string;
  usedFor: string;
  getKeyUrl: string;
  hasCredits: boolean;
}> = {
  openrouter: {
    label: 'OpenRouter',
    description: 'LLM Gateway - Access GPT-4, Claude, Gemini and more',
    usedFor: 'AI Agents (Theme, Music, Visual Director)',
    getKeyUrl: 'https://openrouter.ai/keys',
    hasCredits: true,
  },
  kie: {
    label: 'kie.ai',
    description: 'Image & Music Generation - Nano Banana, Suno AI',
    usedFor: 'Image Generator, Music Composer',
    getKeyUrl: 'https://kie.ai',
    hasCredits: true,
  },
};

export interface Project {
  id: number;
  user_id: number;
  channel_id: number | null;
  title: string;
  description: string | null;
  status: 'draft' | 'processing' | 'completed' | 'failed';
  concept: Record<string, any> | null;
  error_message: string | null;
  scheduled_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  channel?: { id: number; name: string };
  assets?: Asset[];
  job_logs?: JobLog[];
}

export interface Asset {
  id: number;
  project_id: number;
  type: 'music' | 'image' | 'thumbnail';
  filename: string;
  url: string;
  size_bytes: number;
  duration_seconds: number | null;
  metadata: Record<string, any> | null;
  kie_task_id: string | null;
  created_at: string;
}

export interface JobLog {
  id: number;
  project_id: number;
  job_type: string;
  status: 'pending' | 'running' | 'completed' | 'failed';
  payload: Record<string, any> | null;
  result: Record<string, any> | null;
  error_message: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
}

export interface Channel {
  id: number;
  user_id: number;
  name: string;
  platform: string | null;
  description: string | null;
  schedule_config: Record<string, any> | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// Pipeline Interface
export interface Pipeline {
  id: number;
  project_id: number;
  user_id: number;
  pipeline_type: PipelineType;
  mode: 'auto' | 'manual';
  status: 'pending' | 'running' | 'paused' | 'completed' | 'failed';
  current_step: string | null;
  current_step_progress: number;
  config: {
    theme?: string;
    song_brief?: string;
    duration?: number;
    platform?: string;
  };
  steps_state: Record<string, PipelineStepState>;
  error_message: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  project?: { id: number; title: string };
  logs?: PipelineLog[];
}

export interface PipelineStepState {
  status: 'pending' | 'running' | 'completed' | 'failed';
  progress: number;
  result: Record<string, any> | null;
  error: string | null;
  started_at?: string;
  completed_at?: string;
}

export interface PipelineLog {
  id: number;
  pipeline_id: number;
  agent_type: string;
  log_type: 'info' | 'progress' | 'result' | 'error' | 'thinking';
  message: string;
  data: Record<string, any> | null;
  created_at: string;
}

export interface PipelineEvent {
  pipeline_id: number;
  step: string;
  progress: number;
  status: string;
  message?: string;
  timestamp: string;
}

// Agent Config Types
export interface AgentConfig {
  id: number;
  user_id: number;
  agent_type: string;
  name: string;
  system_prompt: string;
  model: string;
  parameters: {
    temperature: number;
    max_tokens: number;
  };
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export const AGENT_TYPES = [
  'theme_director',
  'music_composer',
  'visual_director',
  'image_generator',
  'video_composer',
] as const;

export type AgentType = (typeof AGENT_TYPES)[number];

export const AGENT_TYPE_LABELS: Record<AgentType, string> = {
  theme_director: 'Theme Director',
  music_composer: 'Music Composer',
  visual_director: 'Visual Director',
  image_generator: 'Image Generator',
  video_composer: 'Video Composer',
};

export const PIPELINE_STEPS = AGENT_TYPES;

// Music Video Pipeline Agent Types
export const MUSIC_VIDEO_AGENT_TYPES = [
  'song_architect',
  'suno_expert',
  'song_selector',
  'visual_designer',
] as const;

export type MusicVideoAgentType = (typeof MUSIC_VIDEO_AGENT_TYPES)[number];

export const MUSIC_VIDEO_AGENT_TYPE_LABELS: Record<MusicVideoAgentType, string> = {
  song_architect: 'Song Architect',
  suno_expert: 'Suno Expert',
  song_selector: 'Song Selector',
  visual_designer: 'Visual Designer',
};

// Pipeline Types
export type PipelineType = 'video' | 'music_video';

export const PIPELINE_TYPE_LABELS: Record<PipelineType, string> = {
  video: 'Video Pipeline',
  music_video: 'Music Video Pipeline',
};

// Combined helper to get label for any agent type
export const getAgentTypeLabel = (agentType: string): string => {
  if (agentType in AGENT_TYPE_LABELS) {
    return AGENT_TYPE_LABELS[agentType as AgentType];
  }
  if (agentType in MUSIC_VIDEO_AGENT_TYPE_LABELS) {
    return MUSIC_VIDEO_AGENT_TYPE_LABELS[agentType as MusicVideoAgentType];
  }
  return agentType;
};

// Combined all agent type labels
export const ALL_AGENT_TYPE_LABELS: Record<string, string> = {
  ...AGENT_TYPE_LABELS,
  ...MUSIC_VIDEO_AGENT_TYPE_LABELS,
};

// Projects API
export const projectsApi = {
  list: (params?: { page?: string; status?: string }) =>
    apiClient.cachedGet<PaginatedResponse<Project>>('/projects', params, CACHE_TTL.SHORT),

  create: async (data: { title: string; description?: string; channel_id?: number }) => {
    const result = await apiClient.post<{ message: string; project: Project }>('/projects', data);
    apiClient.invalidateCache('/projects'); // Invalidate project list cache
    return result;
  },

  get: (id: number) => apiClient.cachedGet<{ project: Project }>(`/projects/${id}`, undefined, CACHE_TTL.SHORT),

  update: async (id: number, data: { title?: string; description?: string; channel_id?: number }) => {
    const result = await apiClient.put<{ message: string; project: Project }>(`/projects/${id}`, data);
    apiClient.invalidateCache(`/projects/${id}`);
    apiClient.invalidateCache('/projects');
    return result;
  },

  delete: async (id: number) => {
    const result = await apiClient.delete<{ message: string }>(`/projects/${id}`);
    apiClient.invalidateCache(`/projects/${id}`);
    apiClient.invalidateCache('/projects');
    return result;
  },

  // Status and assets - no cache for real-time data during processing
  status: (id: number) =>
    apiClient.get<{
      project: Project;
      jobs: { total: number; pending: number; running: number; completed: number; failed: number };
      assets: { music: number; images: number };
      progress: number;
    }>(`/projects/${id}/status`),

  assets: (id: number) => apiClient.get<{ assets: Asset[] }>(`/projects/${id}/assets`),

  generateConcept: async (id: number, data: Record<string, any>) => {
    const result = await apiClient.post<{ message: string; project: Project }>(`/projects/${id}/generate-concept`, data);
    apiClient.invalidateCache(`/projects/${id}`);
    return result;
  },

  generateMusic: async (id: number, data: Record<string, any>) => {
    const result = await apiClient.post<{ message: string; project: Project }>(`/projects/${id}/generate-music`, data);
    apiClient.invalidateCache(`/projects/${id}`);
    return result;
  },

  generateImages: async (id: number, data: Record<string, any>) => {
    const result = await apiClient.post<{ message: string; image_count: number; project: Project }>(
      `/projects/${id}/generate-images`,
      data
    );
    apiClient.invalidateCache(`/projects/${id}`);
    return result;
  },

  generateAll: async (id: number, data: Record<string, any>) => {
    const result = await apiClient.post<{ message: string; project: Project; workflow: Record<string, string> }>(
      `/projects/${id}/generate-all`,
      data
    );
    apiClient.invalidateCache(`/projects/${id}`);
    return result;
  },

  download: (id: number) =>
    apiClient.get<{ assets: { type: string; download_url: string; filename: string; size_bytes: number }[] }>(
      `/projects/${id}/download`
    ),
};

// Channels API
export const channelsApi = {
  list: () => apiClient.get<{ channels: Channel[] }>('/channels'),

  create: (data: { name: string; platform?: string; description?: string }) =>
    apiClient.post<{ message: string; channel: Channel }>('/channels', data),

  get: (id: number) => apiClient.get<{ channel: Channel }>(`/channels/${id}`),

  update: (id: number, data: { name?: string; platform?: string; description?: string; is_active?: boolean }) =>
    apiClient.put<{ message: string; channel: Channel }>(`/channels/${id}`, data),

  delete: (id: number) => apiClient.delete<{ message: string }>(`/channels/${id}`),
};

// Pipelines API
export const pipelinesApi = {
  list: (params?: { page?: string }) => apiClient.get<PaginatedResponse<Pipeline>>('/pipelines', params),

  create: (
    projectId: number,
    data: {
      pipeline_type?: PipelineType;
      mode?: 'auto' | 'manual';
      theme?: string;
      song_brief?: string;
      duration?: number;
      platform?: string;
    }
  ) => apiClient.post<{ message: string; pipeline: Pipeline }>(`/pipelines/project/${projectId}`, data),

  get: (id: number) => apiClient.get<{ pipeline: Pipeline }>(`/pipelines/${id}`),

  start: (id: number) => apiClient.post<{ message: string; pipeline: Pipeline }>(`/pipelines/${id}/start`),

  pause: (id: number) => apiClient.post<{ message: string; pipeline: Pipeline }>(`/pipelines/${id}/pause`),

  resume: (id: number) => apiClient.post<{ message: string; pipeline: Pipeline }>(`/pipelines/${id}/resume`),

  cancel: (id: number) => apiClient.post<{ message: string; pipeline: Pipeline }>(`/pipelines/${id}/cancel`),

  runStep: (id: number, step: string) =>
    apiClient.post<{ message: string; pipeline: Pipeline }>(`/pipelines/${id}/step`, { step }),

  logs: (id: number, params?: { agent_type?: string; log_type?: string; page?: string }) =>
    apiClient.get<PaginatedResponse<PipelineLog>>(`/pipelines/${id}/logs`, params),

  stepResult: (id: number, step: string) =>
    apiClient.get<{ step: string; state: PipelineStepState }>(`/pipelines/${id}/step/${step}`),
};

// Agent Configs API
export const agentConfigsApi = {
  list: () =>
    apiClient.get<{
      configs: AgentConfig[];
      grouped: Record<string, AgentConfig[]>;
      agent_types: string[];
    }>('/agent-configs'),

  create: (data: {
    agent_type: string;
    name: string;
    system_prompt: string;
    model?: string;
    parameters?: { temperature?: number; max_tokens?: number };
  }) => apiClient.post<{ message: string; config: AgentConfig }>('/agent-configs', data),

  get: (id: number) => apiClient.get<{ config: AgentConfig }>(`/agent-configs/${id}`),

  update: (
    id: number,
    data: {
      name?: string;
      system_prompt?: string;
      model?: string;
      parameters?: { temperature?: number; max_tokens?: number };
    }
  ) => apiClient.put<{ message: string; config: AgentConfig }>(`/agent-configs/${id}`, data),

  delete: (id: number) => apiClient.delete<{ message: string }>(`/agent-configs/${id}`),

  setDefault: (id: number) => apiClient.post<{ message: string; config: AgentConfig }>(`/agent-configs/${id}/set-default`),

  resetToDefault: (id: number) => apiClient.post<{ message: string; config: AgentConfig }>(`/agent-configs/${id}/reset`),

  getDefaultPrompt: (agentType: string) =>
    apiClient.get<{
      agent_type: string;
      default_prompt: string;
      default_model: string;
      default_parameters: { temperature: number; max_tokens: number };
    }>(`/agent-configs/defaults/${agentType}`),

  test: (id: number, testPrompt: string) =>
    apiClient.post<{ message: string; config: AgentConfig; test_prompt: string }>(`/agent-configs/${id}/test`, {
      test_prompt: testPrompt,
    }),
};

// Unified API object
export const api = {
  ...apiClient,
  setToken: apiClient.setToken.bind(apiClient),
  getToken: apiClient.getToken.bind(apiClient),
  get: apiClient.get.bind(apiClient),
  post: apiClient.post.bind(apiClient),
  put: apiClient.put.bind(apiClient),
  delete: apiClient.delete.bind(apiClient),
  projects: projectsApi,
  channels: channelsApi,
  auth: authApi,
  apiKeys: apiKeysApi,
  pipelines: pipelinesApi,
  agentConfigs: agentConfigsApi,
};
