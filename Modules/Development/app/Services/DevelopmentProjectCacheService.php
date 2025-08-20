<?php

namespace Modules\Development\app\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Services\GeneralService;
use Modules\Hrd\Models\Employee;

class DevelopmentProjectCacheService
{
    private const BASE_KEY = 'projects:all';
    private const FILTERED_PREFIX = 'projects:filtered:';
    private const TRACKED_KEYS = 'project_cache_keys';
    private const BASE_TTL = 1800; // 30 minutes
    private const FILTERED_TTL = 600; // 10 minutes
    private const MAX_CACHED_FILTERS = 50;

    public function getFilteredProjects(array $filters, int $page = 1, int $perPage = 15, bool $withoutPagination = false): array
    {
        $filterHash = $this->generateFilterHash($filters, $page, $perPage);
        $filteredKey = self::FILTERED_PREFIX . $filterHash;

        // Try filtered cache first
        if (Cache::has($filteredKey)) {
            $this->updateKeyAccess($filteredKey);
            return Cache::get($filteredKey);
        }

        // Get or create base cache
        $allProjects = Cache::remember(self::BASE_KEY, self::BASE_TTL, function () {
            return \Modules\Development\Models\DevelopmentProject::selectRaw('*')
                ->get()
                ->toArray();
        });

        // Apply filters and pagination
        $filtered = $this->applyFilters($allProjects, $filters);
        $total = count($filtered);

        $offset = ($page - 1) * $perPage;

        $paginatedData = array_slice($filtered, $offset, $perPage);

        $result = [
            'data' => $paginatedData,
            'total' => $total,
            // 'per_page' => $perPage,
            // 'current_page' => $page,
            // 'last_page' => ceil($total / $perPage),
            // 'from' => $offset + 1,
            // 'to' => min($offset + $perPage, $total)
        ];

        // Cache the result
        Cache::put($filteredKey, $result, self::FILTERED_TTL);
        $this->trackCacheKey($filteredKey);

        return $result;
    }

    private function trackCacheKey(string $key): void
    {
        $trackedKeys = Cache::get(self::TRACKED_KEYS, []);
        
        // Add new key with timestamp
        $trackedKeys[$key] = now()->timestamp;
        
        // Remove expired or excess keys
        if (count($trackedKeys) > self::MAX_CACHED_FILTERS) {
            // Sort by access time and keep most recent
            arsort($trackedKeys);
            $trackedKeys = array_slice($trackedKeys, 0, self::MAX_CACHED_FILTERS, true);
        }
        
        Cache::put(self::TRACKED_KEYS, $trackedKeys, self::BASE_TTL);
    }

    private function updateKeyAccess(string $key): void
    {
        $trackedKeys = Cache::get(self::TRACKED_KEYS, []);
        if (isset($trackedKeys[$key])) {
            $trackedKeys[$key] = now()->timestamp;
            Cache::put(self::TRACKED_KEYS, $trackedKeys, self::BASE_TTL);
        }
    }

    public function invalidateAllProjectCaches(): void
    {
        // Clear base cache
        Cache::forget(self::BASE_KEY);
        
        // Clear all tracked filtered caches
        $trackedKeys = Cache::get(self::TRACKED_KEYS, []);
        foreach (array_keys($trackedKeys) as $key) {
            Cache::forget($key);
        }
        
        // Clear tracking
        Cache::forget(self::TRACKED_KEYS);
    }

    private function applyFilters(array $projects, array $filters): array
    {
        return array_filter($projects, function ($project) use ($filters) {
            // Status filter
            if (!empty($filters['status'])) {
                if (is_array($filters['status'])) {
                    // If status is array [1,2], check if project status is in the array
                    if (!in_array($project['status'], $filters['status'])) {
                        return false;
                    }
                } else {
                    // If status is single value, check exact match
                    if ($project['status'] != $filters['status']) {
                        return false;
                    }
                }
            }

            // Person in charge filter
            // filters['pics'] will have these structure [<employee_uid>, ...]. We need to convert uid to id by calling getIdFromUid method from generalService class
            if (!empty($filters['pics'])) {
                $picIds = array_map(function ($uid) {
                    return (new GeneralService)->getIdFromUid($uid, new Employee());
                }, $filters['pics']);

                if (!in_array($project['user_id'], $picIds)) {
                    return false;
                }
            }

            // Name filter (search)
            if (!empty($filters['name'])) {
                $searchTerm = strtolower($filters['name']);
                $projectName = strtolower($project['name']);
                if (strpos($projectName, $searchTerm) === false) {
                    return false;
                }
            }

            // Date range filter
            if (!empty($filters['start_date'])) {
                if (strtotime($project['project_date']) < strtotime($filters['start_date'])) {
                    return false;
                }
            }

            if (!empty($filters['end_date'])) {
                if (strtotime($project['project_date']) > strtotime($filters['end_date'])) {
                    return false;
                }
            }

            return true;
        });
    }

    private function generateFilterHash(array $filters, int $page, int $perPage): string
    {
        // Remove empty values
        $cleanFilters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '' && $value !== [];
        });

        // Add pagination to hash
        $cleanFilters['_page'] = $page;
        $cleanFilters['_per_page'] = $perPage;

        // Sort for consistent hash
        ksort($cleanFilters);

        return md5(json_encode($cleanFilters));
    }

    public function invalidateSpecificFilter(array $filters): void
    {
        $pattern = self::FILTERED_PREFIX . $this->generateFilterHash($filters, 1, 15);
        Cache::forget($pattern);
    }

    public function warmupCommonFilters(): void
    {
        $commonFilters = [
            ['status' => 'active'],
            ['status' => 'completed'],
            ['priority' => 'high'],
            [] // No filters (all projects)
        ];

        foreach ($commonFilters as $filters) {
            $this->getFilteredProjects($filters);
        }
    }
}