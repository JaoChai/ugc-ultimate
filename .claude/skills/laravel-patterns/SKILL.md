---
name: laravel-patterns
description: "Laravel development patterns สำหรับ UGCUNTIMATE - Service pattern, API responses, Queue jobs, Eloquent, Error handling, R2 storage"
---

# Laravel Development Patterns

Best practices และ patterns สำหรับ Laravel development ใน UGCUNTIMATE project

## When to Apply

Reference these patterns when:
- สร้าง API endpoints ใหม่
- เขียน Service classes
- จัดการ Queue jobs
- ทำงานกับ Eloquent models
- จัดการ file storage (R2)

---

## 1. Service Pattern

แยก business logic ออกจาก Controller เข้า Service class

```php
// ✅ GOOD: Service class with clear responsibility
namespace App\Services;

class KieApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.kie.base_url');
        $this->apiKey = config('services.kie.api_key');
    }

    public function generateVideo(array $params): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post($this->baseUrl . '/v1/videos', $params);

        if ($response->failed()) {
            throw $this->handleError($response);
        }

        return $response->json();
    }

    private function handleError(Response $response): RuntimeException
    {
        $status = $response->status();
        $body = $response->json();

        return match(true) {
            $status === 401 => new RuntimeException('Invalid API key', 401),
            $status === 429 => new RuntimeException('Rate limit exceeded', 429),
            $status === 402 => new RuntimeException('Insufficient credits', 402),
            default => new RuntimeException($body['message'] ?? 'API error', $status),
        };
    }
}
```

```php
// ❌ BAD: Business logic in Controller
class VideoController extends Controller
{
    public function generate(Request $request)
    {
        // ไม่ควรมี HTTP calls และ business logic ใน controller
        $response = Http::post('https://api.kie.ai/...');
        // ...
    }
}

// ✅ GOOD: Controller delegates to Service
class VideoController extends Controller
{
    public function generate(Request $request, KieApiService $service)
    {
        $result = $service->generateVideo($request->validated());
        return response()->json($result);
    }
}
```

---

## 2. API Response Pattern

Standardized JSON response structure

```php
// ✅ Consistent API Response Format
class ApiController extends Controller
{
    protected function success($data = null, string $message = 'Success', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message, int $status = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}

// Usage
public function store(StoreProjectRequest $request)
{
    try {
        $project = $this->projectService->create($request->validated());
        return $this->success($project, 'Project created', 201);
    } catch (Exception $e) {
        return $this->error($e->getMessage(), 500);
    }
}
```

### HTTP Status Codes

| Status | When to Use |
|--------|-------------|
| 200 | Success (GET, PUT, PATCH) |
| 201 | Created (POST) |
| 204 | No Content (DELETE) |
| 400 | Bad Request (validation failed) |
| 401 | Unauthorized (no token) |
| 403 | Forbidden (no permission) |
| 404 | Not Found |
| 422 | Unprocessable Entity (business logic error) |
| 500 | Internal Server Error |

---

## 3. Error Handling Pattern

ใช้ RuntimeException + match() สำหรับ API errors

```php
// ✅ GOOD: Structured error handling
public function callExternalApi(): array
{
    try {
        $response = Http::timeout(30)->post($this->url, $params);

        if ($response->failed()) {
            throw $this->mapStatusToException($response);
        }

        return $response->json();

    } catch (ConnectionException $e) {
        throw new RuntimeException('Service unavailable', 503);
    } catch (RequestException $e) {
        throw new RuntimeException('Request timeout', 504);
    }
}

private function mapStatusToException(Response $response): RuntimeException
{
    $status = $response->status();
    $message = $response->json('message', 'Unknown error');

    return match(true) {
        $status === 401 => new RuntimeException('Invalid API key', 401),
        $status === 402 => new RuntimeException('Insufficient credits', 402),
        $status === 429 => new RuntimeException('Rate limit exceeded, retry later', 429),
        $status >= 500 => new RuntimeException('External service error', 502),
        default => new RuntimeException($message, $status),
    };
}
```

### In Queue Jobs

```php
class GenerateVideoJob implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(KieApiService $service): void
    {
        try {
            $result = $service->generateVideo($this->params);
            $this->project->update(['status' => 'completed']);

        } catch (RuntimeException $e) {
            // Decide based on error code
            if (in_array($e->getCode(), [401, 402])) {
                // Don't retry - permanent failure
                $this->fail($e);
            }

            // 429, 503, 504 - will auto-retry
            throw $e;
        }
    }
}
```

---

## 4. Queue Jobs Pattern

**CRITICAL:** ทุก generation job ต้องรันผ่าน queue ห้ามรัน synchronously

```php
// ✅ GOOD: Dispatch to queue
class ProjectController extends Controller
{
    public function generate(Project $project)
    {
        GenerateConceptJob::dispatch($project);

        return response()->json([
            'success' => true,
            'message' => 'Generation started',
        ]);
    }
}

// ❌ BAD: Synchronous execution (will timeout)
public function generate(Project $project)
{
    $service = new KieApiService();
    $result = $service->generateVideo($project->toArray()); // Timeout!
}
```

### Job Chaining

```php
// ✅ Pipeline pattern for video generation
class GenerateConceptJob implements ShouldQueue
{
    public function handle(): void
    {
        $concept = $this->conceptService->generate($this->project);
        $this->project->update(['concept' => $concept]);

        // Chain next jobs
        GenerateMusicJob::dispatch($this->project);
        GenerateImageJob::dispatch($this->project);
    }
}

class ComposeVideoJob implements ShouldQueue
{
    public function handle(): void
    {
        // Wait for all assets
        if (!$this->project->hasAllAssets()) {
            $this->release(30); // Retry in 30 seconds
            return;
        }

        $this->ffmpegService->compose($this->project);
    }
}
```

### Job Logging

```php
// ✅ Log job progress to job_logs table
public function handle(): void
{
    $this->logProgress('Starting video generation');

    try {
        $result = $this->service->generate();
        $this->logProgress('Generation completed', ['result' => $result]);

    } catch (Exception $e) {
        $this->logProgress('Generation failed', ['error' => $e->getMessage()]);
        throw $e;
    }
}

private function logProgress(string $message, array $data = []): void
{
    JobLog::create([
        'project_id' => $this->project->id,
        'job_type' => class_basename($this),
        'message' => $message,
        'data' => $data,
    ]);
}
```

---

## 5. Eloquent Patterns

### Eager Loading (Prevent N+1)

```php
// ❌ BAD: N+1 Query Problem
$projects = Project::all();
foreach ($projects as $project) {
    echo $project->user->name; // N additional queries!
}

// ✅ GOOD: Eager Loading
$projects = Project::with('user')->get();
foreach ($projects as $project) {
    echo $project->user->name; // No additional queries
}

// ✅ GOOD: Nested Eager Loading
$projects = Project::with(['user', 'assets', 'assets.media'])->get();
```

### Query Scopes

```php
// ✅ Reusable query scopes
class Project extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

// Usage
$projects = Project::active()
    ->forUser($user)
    ->recent(30)
    ->with('assets')
    ->get();
```

### Chunking for Large Datasets

```php
// ✅ GOOD: Process in chunks to save memory
Project::where('status', 'pending')
    ->chunk(100, function ($projects) {
        foreach ($projects as $project) {
            ProcessProjectJob::dispatch($project);
        }
    });

// ❌ BAD: Load all into memory
$projects = Project::where('status', 'pending')->get(); // Memory issue!
```

---

## 6. Validation Pattern

### Form Request

```php
// ✅ Dedicated Form Request class
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or check permissions
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['video', 'image', 'audio'])],
            'settings' => ['nullable', 'array'],
            'settings.duration' => ['nullable', 'integer', 'min:1', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required',
            'type.in' => 'Invalid project type',
        ];
    }
}

// Usage in Controller
public function store(StoreProjectRequest $request)
{
    // Already validated
    $project = Project::create($request->validated());
}
```

---

## 7. R2 Storage Pattern

**CRITICAL:** Upload ต้องผ่าน R2StorageService เท่านั้น ห้ามใช้ local filesystem

```php
// ✅ GOOD: Use R2StorageService
class R2StorageService
{
    public function upload(string $path, $content, array $options = []): string
    {
        $disk = Storage::disk('r2');

        $disk->put($path, $content, $options['visibility'] ?? 'public');

        return $this->getPublicUrl($path);
    }

    public function getPublicUrl(string $path): string
    {
        return config('filesystems.disks.r2.url') . '/' . $path;
    }

    public function delete(string $path): bool
    {
        return Storage::disk('r2')->delete($path);
    }
}

// Usage
$url = $this->r2Service->upload(
    "projects/{$project->id}/video.mp4",
    file_get_contents($tempFile)
);

// ❌ BAD: Local filesystem in production
Storage::disk('local')->put('video.mp4', $content); // Won't work!
```

---

## 8. Authentication Pattern

ใช้ Sanctum token authentication

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/generate', [ProjectController::class, 'generate']);
});

// Controller with authorization
class ProjectController extends Controller
{
    public function show(Project $project)
    {
        $this->authorize('view', $project); // Policy check

        return response()->json($project->load('assets'));
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return response()->json($project);
    }
}
```

---

## 9. Type Safety

**CRITICAL:** ต้อง cast types ก่อน save เพื่อป้องกัน database errors

```php
// ✅ GOOD: Explicit type casting
public function updateCredits(float $credits): void
{
    $this->user->update([
        'credits_remaining' => (int) $credits, // Cast float to int
    ]);
}

// ❌ BAD: No type casting (causes PostgreSQL errors)
public function updateCredits($credits): void
{
    $this->user->update([
        'credits_remaining' => $credits, // Error: invalid input syntax for type integer
    ]);
}
```

### Model Casts

```php
class Project extends Model
{
    protected $casts = [
        'settings' => 'array',
        'metadata' => 'json',
        'is_public' => 'boolean',
        'credits_used' => 'integer',
        'created_at' => 'datetime',
    ];
}
```

---

## Quick Reference

| Pattern | When to Use |
|---------|-------------|
| Service Pattern | External API calls, complex business logic |
| Form Request | Input validation |
| Queue Jobs | Long-running tasks (>5 seconds) |
| Eager Loading | Loading relationships |
| R2StorageService | All file uploads |
| Type Casting | Before database operations |

---

## Anti-Patterns to Avoid

| Anti-Pattern | Problem | Solution |
|--------------|---------|----------|
| Business logic in Controller | Hard to test, reuse | Use Service classes |
| Sync API calls | Request timeout | Use Queue Jobs |
| N+1 queries | Performance | Eager loading |
| Local storage | Doesn't work in Railway | Use R2 |
| Missing type casts | PostgreSQL errors | Cast before save |
| Generic exceptions | Hard to handle | Use specific error codes |
