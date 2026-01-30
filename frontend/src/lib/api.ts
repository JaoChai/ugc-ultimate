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
  service: string;
  name: string;
  key_masked: string;
  credits_remaining: number;
  is_active: boolean;
  last_used_at: string | null;
  created_at: string;
}

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
  type: 'music' | 'image' | 'video_clip' | 'final_video' | 'thumbnail';
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

// Projects API
export const projectsApi = {
  list: (params?: { page?: string; status?: string }) =>
    apiClient.get<PaginatedResponse<Project>>('/projects', params),

  create: (data: { title: string; description?: string; channel_id?: number }) =>
    apiClient.post<{ message: string; project: Project }>('/projects', data),

  get: (id: number) => apiClient.get<{ project: Project }>(`/projects/${id}`),

  update: (id: number, data: { title?: string; description?: string; channel_id?: number }) =>
    apiClient.put<{ message: string; project: Project }>(`/projects/${id}`, data),

  delete: (id: number) => apiClient.delete<{ message: string }>(`/projects/${id}`),

  status: (id: number) =>
    apiClient.get<{
      project: Project;
      jobs: { total: number; pending: number; running: number; completed: number; failed: number };
      assets: { music: number; images: number; video_clips: number; final_video: number };
      progress: number;
    }>(`/projects/${id}/status`),

  assets: (id: number) => apiClient.get<{ assets: Asset[] }>(`/projects/${id}/assets`),

  generateConcept: (id: number, data: Record<string, any>) =>
    apiClient.post<{ message: string; project: Project }>(`/projects/${id}/generate-concept`, data),

  generateMusic: (id: number, data: Record<string, any>) =>
    apiClient.post<{ message: string; project: Project }>(`/projects/${id}/generate-music`, data),

  generateImages: (id: number, data: Record<string, any>) =>
    apiClient.post<{ message: string; image_count: number; project: Project }>(
      `/projects/${id}/generate-images`,
      data
    ),

  generateVideos: (id: number, data: Record<string, any>) =>
    apiClient.post<{ message: string; video_count: number; project: Project }>(
      `/projects/${id}/generate-videos`,
      data
    ),

  generateAll: (id: number, data: Record<string, any>) =>
    apiClient.post<{ message: string; project: Project; workflow: Record<string, string> }>(
      `/projects/${id}/generate-all`,
      data
    ),

  compose: (id: number, data: Record<string, any>) =>
    apiClient.post<{ message: string; project: Project }>(`/projects/${id}/compose`, data),

  recompose: (id: number, data: Record<string, any>) =>
    apiClient.post<{ message: string; project: Project }>(`/projects/${id}/recompose`, data),

  download: (id: number) =>
    apiClient.get<{ download_url: string; filename: string; size_bytes: number; duration_seconds: number }>(
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
};
