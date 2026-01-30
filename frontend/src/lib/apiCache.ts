/**
 * Simple in-memory cache for API responses
 * - Automatic TTL-based expiration
 * - Pattern-based invalidation
 * - Memory-efficient with max entries limit
 */

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  ttl: number;
}

class ApiCache {
  private cache = new Map<string, CacheEntry<unknown>>();
  private maxEntries = 100;
  private defaultTTL = 30000; // 30 seconds

  /**
   * Get cached data if not expired
   */
  get<T>(key: string): T | null {
    const entry = this.cache.get(key);
    if (!entry) return null;

    const isExpired = Date.now() - entry.timestamp > entry.ttl;
    if (isExpired) {
      this.cache.delete(key);
      return null;
    }

    return entry.data as T;
  }

  /**
   * Set cache entry with optional custom TTL
   */
  set<T>(key: string, data: T, ttl?: number): void {
    // Evict oldest entries if cache is full
    if (this.cache.size >= this.maxEntries) {
      const oldestKey = this.cache.keys().next().value;
      if (oldestKey) this.cache.delete(oldestKey);
    }

    this.cache.set(key, {
      data,
      timestamp: Date.now(),
      ttl: ttl ?? this.defaultTTL,
    });
  }

  /**
   * Invalidate cache entries matching a pattern
   * @param pattern - String pattern to match against cache keys
   */
  invalidate(pattern?: string): void {
    if (!pattern) {
      this.cache.clear();
      return;
    }

    for (const key of this.cache.keys()) {
      if (key.includes(pattern)) {
        this.cache.delete(key);
      }
    }
  }

  /**
   * Check if a key exists and is not expired
   */
  has(key: string): boolean {
    return this.get(key) !== null;
  }

  /**
   * Get cache stats for debugging
   */
  getStats(): { size: number; maxEntries: number } {
    return {
      size: this.cache.size,
      maxEntries: this.maxEntries,
    };
  }
}

// Singleton instance
export const apiCache = new ApiCache();

/**
 * Helper to create a cache key from endpoint and params
 */
export function createCacheKey(endpoint: string, params?: Record<string, string>): string {
  if (!params || Object.keys(params).length === 0) {
    return endpoint;
  }
  const sortedParams = Object.keys(params)
    .sort()
    .map((key) => `${key}=${params[key]}`)
    .join('&');
  return `${endpoint}?${sortedParams}`;
}

/**
 * Cache TTL presets (in milliseconds)
 */
export const CACHE_TTL = {
  SHORT: 15000,    // 15 seconds - for rapidly changing data
  DEFAULT: 30000,  // 30 seconds - default
  MEDIUM: 60000,   // 1 minute - for semi-static data
  LONG: 300000,    // 5 minutes - for rarely changing data
} as const;
