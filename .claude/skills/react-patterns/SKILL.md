---
name: react-patterns
description: "React patterns เฉพาะ UGCUNTIMATE - API caching, code splitting, memory management, WebSocket, Context patterns"
---

# React Patterns for UGCUNTIMATE

Project-specific React patterns ที่พัฒนาจาก decisions และ bug fixes

## When to Apply

Reference these patterns when:
- จัดการ API calls และ caching
- ทำ code splitting
- จัดการ state ที่อาจ grow unbounded
- ทำงานกับ WebSocket (Reverb)
- ใช้ Context สำหรับ global state

---

## 1. API Cache Pattern

In-memory cache with TTL-based expiration (ตาม decision ที่เลือก)

```typescript
// lib/apiCache.ts
interface CacheEntry<T> {
  data: T
  timestamp: number
  ttl: number
}

class ApiCache {
  private cache = new Map<string, CacheEntry<unknown>>()
  private defaultTTL = 5 * 60 * 1000 // 5 minutes

  get<T>(key: string): T | null {
    const entry = this.cache.get(key) as CacheEntry<T> | undefined

    if (!entry) return null

    if (Date.now() - entry.timestamp > entry.ttl) {
      this.cache.delete(key)
      return null
    }

    return entry.data
  }

  set<T>(key: string, data: T, ttl = this.defaultTTL): void {
    this.cache.set(key, {
      data,
      timestamp: Date.now(),
      ttl,
    })
  }

  invalidate(pattern: string): void {
    // Pattern-based invalidation
    for (const key of this.cache.keys()) {
      if (key.includes(pattern)) {
        this.cache.delete(key)
      }
    }
  }

  clear(): void {
    this.cache.clear()
  }
}

export const apiCache = new ApiCache()
```

### Usage with API calls

```typescript
// hooks/useProjects.ts
export function useProjects() {
  const [projects, setProjects] = useState<Project[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchProjects = async () => {
      // Check cache first
      const cached = apiCache.get<Project[]>('projects')
      if (cached) {
        setProjects(cached)
        setLoading(false)
        return
      }

      // Fetch from API
      const data = await api.get('/projects')
      apiCache.set('projects', data, 5 * 60 * 1000) // 5 min TTL
      setProjects(data)
      setLoading(false)
    }

    fetchProjects()
  }, [])

  const invalidateCache = () => apiCache.invalidate('projects')

  return { projects, loading, invalidateCache }
}
```

### What NOT to cache

```typescript
// ❌ Don't cache real-time data
// - Project status (changes during generation)
// - Asset progress
// - WebSocket events

// ✅ Safe to cache
// - User profile
// - Project list (invalidate on create/delete)
// - Settings
// - API keys list
```

---

## 2. Code Splitting Pattern

React.lazy + Suspense (ตาม decision ที่เลือก)

```typescript
// App.tsx - Route-based splitting
import { lazy, Suspense } from 'react'
import { Routes, Route } from 'react-router-dom'

// ✅ Lazy load heavy pages
const Dashboard = lazy(() => import('./pages/Dashboard'))
const ProjectDetail = lazy(() => import('./pages/ProjectDetail'))
const Settings = lazy(() => import('./pages/Settings'))

// Loading component
function PageLoader() {
  return (
    <div className="flex items-center justify-center h-screen">
      <Spinner size="lg" />
    </div>
  )
}

export function App() {
  return (
    <Suspense fallback={<PageLoader />}>
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/projects/:id" element={<ProjectDetail />} />
        <Route path="/settings" element={<Settings />} />
      </Routes>
    </Suspense>
  )
}
```

### Component-level splitting

```typescript
// ✅ Heavy components that aren't always visible
const VideoPlayer = lazy(() => import('./components/VideoPlayer'))
const ChartDashboard = lazy(() => import('./components/ChartDashboard'))

function ProjectDetail() {
  const [showVideo, setShowVideo] = useState(false)

  return (
    <div>
      {showVideo && (
        <Suspense fallback={<VideoSkeleton />}>
          <VideoPlayer />
        </Suspense>
      )}
    </div>
  )
}
```

### Preloading

```typescript
// Preload on hover for better UX
const ProjectDetail = lazy(() => import('./pages/ProjectDetail'))

function ProjectCard({ project }: { project: Project }) {
  const preload = () => {
    // Trigger dynamic import on hover
    import('./pages/ProjectDetail')
  }

  return (
    <Link
      to={`/projects/${project.id}`}
      onMouseEnter={preload}
    >
      {project.name}
    </Link>
  )
}
```

---

## 3. Memory Management Pattern

ป้องกัน memory leak จาก unbounded arrays (จาก bug fix)

```typescript
// ✅ GOOD: Limit array sizes
const MAX_EVENTS = 100
const MAX_LOGS = 500
const MAX_COMPLETED_STEPS = 50

function usePipelineSocket(projectId: string) {
  const [events, setEvents] = useState<PipelineEvent[]>([])
  const [logs, setLogs] = useState<LogEntry[]>([])
  const [completedSteps, setCompletedSteps] = useState<string[]>([])

  useEffect(() => {
    const channel = echo.channel(`project.${projectId}`)

    channel.listen('PipelineEvent', (event: PipelineEvent) => {
      setEvents(prev => {
        const updated = [...prev, event]
        // ✅ Keep only last N items
        return updated.slice(-MAX_EVENTS)
      })
    })

    channel.listen('LogEntry', (log: LogEntry) => {
      setLogs(prev => {
        const updated = [...prev, log]
        return updated.slice(-MAX_LOGS)
      })
    })

    channel.listen('StepCompleted', (step: string) => {
      setCompletedSteps(prev => {
        const updated = [...prev, step]
        return updated.slice(-MAX_COMPLETED_STEPS)
      })
    })

    return () => channel.stopListening()
  }, [projectId])

  return { events, logs, completedSteps }
}
```

### General rule

```typescript
// ❌ BAD: Unbounded growth
setItems(prev => [...prev, newItem])

// ✅ GOOD: With limit
const MAX_ITEMS = 100
setItems(prev => [...prev, newItem].slice(-MAX_ITEMS))

// ✅ GOOD: Or use a circular buffer approach
setItems(prev => {
  if (prev.length >= MAX_ITEMS) {
    return [...prev.slice(1), newItem]
  }
  return [...prev, newItem]
})
```

---

## 4. WebSocket Pattern (Reverb)

```typescript
// hooks/useProjectSocket.ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Initialize Echo (usually in main.tsx)
declare global {
  interface Window {
    Echo: Echo
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher
window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: false,
  disableStats: true,
})

// Hook for project updates
export function useProjectSocket(projectId: string) {
  const [status, setStatus] = useState<ProjectStatus>('pending')
  const [progress, setProgress] = useState(0)

  useEffect(() => {
    const channel = window.Echo.private(`project.${projectId}`)

    channel
      .listen('ProjectStatusChanged', (e: { status: ProjectStatus }) => {
        setStatus(e.status)
      })
      .listen('ProjectProgress', (e: { progress: number }) => {
        setProgress(e.progress)
      })

    // Cleanup
    return () => {
      channel.stopListening('ProjectStatusChanged')
      channel.stopListening('ProjectProgress')
      window.Echo.leave(`project.${projectId}`)
    }
  }, [projectId])

  return { status, progress }
}
```

### Error handling for WebSocket

```typescript
export function useProjectSocket(projectId: string) {
  const [connected, setConnected] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const channel = window.Echo.private(`project.${projectId}`)

    channel
      .subscribed(() => {
        setConnected(true)
        setError(null)
      })
      .error((err: Error) => {
        setConnected(false)
        setError(err.message)
        console.error('WebSocket error:', err)
      })

    // ... listeners

    return () => window.Echo.leave(`project.${projectId}`)
  }, [projectId])

  return { connected, error }
}
```

---

## 5. Context Patterns

### Auth Context

```typescript
// contexts/AuthContext.tsx
interface AuthContextType {
  user: User | null
  token: string | null
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  isAuthenticated: boolean
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(
    () => localStorage.getItem('token')
  )

  const login = async (email: string, password: string) => {
    const response = await api.post('/auth/login', { email, password })
    setToken(response.token)
    setUser(response.user)
    localStorage.setItem('token', response.token)
  }

  const logout = () => {
    setToken(null)
    setUser(null)
    localStorage.removeItem('token')
    apiCache.clear() // Clear cache on logout
  }

  return (
    <AuthContext.Provider value={{
      user,
      token,
      login,
      logout,
      isAuthenticated: !!token,
    }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}
```

### Theme Context

```typescript
// contexts/ThemeContext.tsx
type Theme = 'light' | 'dark' | 'system'

interface ThemeContextType {
  theme: Theme
  setTheme: (theme: Theme) => void
  resolvedTheme: 'light' | 'dark'
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined)

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const [theme, setTheme] = useState<Theme>(
    () => (localStorage.getItem('theme') as Theme) || 'system'
  )

  const resolvedTheme = useMemo(() => {
    if (theme === 'system') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light'
    }
    return theme
  }, [theme])

  useEffect(() => {
    document.documentElement.classList.toggle('dark', resolvedTheme === 'dark')
    localStorage.setItem('theme', theme)
  }, [theme, resolvedTheme])

  return (
    <ThemeContext.Provider value={{ theme, setTheme, resolvedTheme }}>
      {children}
    </ThemeContext.Provider>
  )
}

export function useTheme() {
  const context = useContext(ThemeContext)
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider')
  }
  return context
}
```

---

## 6. API URL Pattern

**CRITICAL:** Frontend `VITE_API_URL` ต้องไม่มี `/api` ต่อท้าย

```typescript
// lib/api.ts
const API_URL = import.meta.env.VITE_API_URL // e.g., http://localhost:8000

// ✅ GOOD: api.ts appends /api
export const api = {
  get: (path: string) => fetch(`${API_URL}/api${path}`),
  post: (path: string, data: unknown) => fetch(`${API_URL}/api${path}`, {
    method: 'POST',
    body: JSON.stringify(data),
  }),
}

// .env
// ✅ GOOD
VITE_API_URL=http://localhost:8000

// ❌ BAD - will result in /api/api/...
VITE_API_URL=http://localhost:8000/api
```

---

## 7. Error Boundary Pattern

```typescript
// components/ErrorBoundary.tsx
interface Props {
  children: React.ReactNode
  fallback?: React.ReactNode
}

interface State {
  hasError: boolean
  error: Error | null
}

export class ErrorBoundary extends React.Component<Props, State> {
  state: State = { hasError: false, error: null }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error }
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    console.error('Error caught by boundary:', error, info)
    // Could send to error tracking service
  }

  render() {
    if (this.state.hasError) {
      return this.props.fallback || (
        <div className="p-4 bg-red-50 text-red-600 rounded">
          <h2>Something went wrong</h2>
          <p>{this.state.error?.message}</p>
          <button
            onClick={() => this.setState({ hasError: false })}
            className="mt-2 btn btn-primary"
          >
            Try again
          </button>
        </div>
      )
    }

    return this.props.children
  }
}

// Usage - wrap critical sections
<ErrorBoundary fallback={<ProjectErrorFallback />}>
  <ProjectDetail />
</ErrorBoundary>
```

---

## Quick Reference

| Pattern | When to Use |
|---------|-------------|
| API Cache | Static data ที่ไม่เปลี่ยนบ่อย |
| Code Splitting | Heavy pages/components |
| Memory Limits | Arrays ที่ grow จาก events |
| WebSocket Hook | Real-time updates |
| Context | Global state (auth, theme) |
| Error Boundary | Graceful error handling |

---

## Anti-Patterns to Avoid

| Anti-Pattern | Problem | Solution |
|--------------|---------|----------|
| Unbounded arrays | Memory leak | Limit with `.slice(-MAX)` |
| Cache real-time data | Stale data | Only cache static data |
| API URL with `/api` | Double `/api/api` | Remove from env |
| No error boundaries | App crashes | Wrap critical sections |
| No cleanup in useEffect | Memory leak | Return cleanup function |
