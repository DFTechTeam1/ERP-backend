<?php

namespace Modules\Development\app\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Services\GeneralService;
use Illuminate\Database\Eloquent\Collection;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Repository\DevelopmentProjectRepository;
use Modules\Hrd\Models\Employee;

class DevelopmentProjectCacheService
{
    private const BASE_KEY = 'projects:all';
    private const FILTERED_PREFIX = 'projects:filtered:';
    private const TRACKED_KEYS = 'project_cache_keys';
    private const BASE_TTL = 1800; // 30 minutes
    private const FILTERED_TTL = 600; // 10 minutes
    private const MAX_CACHED_FILTERS = 50;
    private const DEFAULT_SELECT_TABLE = 'id,uid,name,description,status,project_date,created_by';
    private const DEFAULT_RELATIONS = [
        'pics:id,development_project_id,employee_id',
        'pics.employee:id,nickname'
    ];

    private DevelopmentProjectRepository $repo;

    public function __construct(DevelopmentProjectRepository $repo)
    {
        // Ensure the cache keys are initialized
        if (!Cache::has(self::TRACKED_KEYS)) {
            Cache::put(self::TRACKED_KEYS, [], self::BASE_TTL);
        }

        $this->repo = $repo;
    }

    /**
     * Format the project output for caching.
     *
     * @param DevelopmentProject|Collection $project
     * @return array
     */
    public function formatingProjectOutput(DevelopmentProject|Collection $project): array
    {
        return [
            'id' => $project->id,
            'uid' => $project->uid,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'status_text' => $project->status->label(),
            'status_color' => $project->status->color(),
            'project_date' => $project->project_date ? $project->project_date->format('Y-m-d') : null,
            'project_date_text' => $project->project_date_text,
            'created_by' => $project->created_by,
            'pic_name' => $project->pics->pluck('employee.nickname')->implode(','),
            'total_task' => $project->tasks->count(),
            'pics' => $project->pics->map(function ($pic) {
                return [
                    'id' => $pic->employee_id,
                    'nickname' => $pic->employee->nickname
                ];
            })->toArray(),
            'pic_uids' => $project->pics->pluck('employee.uid')->toArray()
        ];
    }

    /**
     * Store all project list to cache.
     * 
     * @return array
     */
    public function storeAllProjectListToCache(): array
    {
        return Cache::remember(self::BASE_KEY, self::BASE_TTL, function () {
            $output = $this->repo->list(
                select: self::DEFAULT_SELECT_TABLE,
                relation: self::DEFAULT_RELATIONS,
                orderBy: 'project_date asc'
            );

            $output = $output->map(function ($project) {
                return $this->formatingProjectOutput($project);
            })->toArray();

            return $output;
        });
    }

    /**
     * Get filtered projects from cache or database.
     * 
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @param bool $withoutPagination
     * 
     * @return array
     */
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
        $allProjects = $this->storeAllProjectListToCache();

        // Apply filters and pagination
        $filtered = $this->applyFilters($allProjects, $filters);
        $total = count($filtered);

        $offset = ($page - 1) * $perPage;

        $paginatedData = array_slice($filtered, $offset, $perPage);

        $result = [
            'data' => $paginatedData,
            'total' => $total,
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

    public function deleteSpecificProjectByUid(string $projectUid): void
    {
        $key = self::BASE_KEY;

        $currentAllProjects = Cache::get($key, []);
        $currentAllProjects = array_filter($currentAllProjects, fn($project) => $project['uid'] !== $projectUid);
        Cache::put($key, $currentAllProjects, self::BASE_TTL);
    }

    /**
     * Push a new project to the all projects cache.
     */
    public function pushNewProjectToAllProjectCache(string $projectUid): void
    {
        $project = $this->repo->show(
            uid: $projectUid,
            select: self::DEFAULT_SELECT_TABLE,
            relation: self::DEFAULT_RELATIONS
        );

        $project = $this->formatingProjectOutput($project);

        $key = self::BASE_KEY;

        $currentAllProjects = Cache::get($key, []);
        
        $currentAllProjects[] = $project;
        Cache::put($key, $currentAllProjects, self::BASE_TTL);
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
                    if (!in_array($project['status']->value, $filters['status'])) {
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
                if (!in_array($project['pic_uids'], $filters['pics'])) {
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

    public function invalidateSpecificFilter(array $filters, int $page, int $perPage): void
    {
        $pattern = self::FILTERED_PREFIX . $this->generateFilterHash($filters, $page, $perPage);
        Cache::forget($pattern);
    }

    // invalidate all cache except the base key
    public function invalidateAllCacheExceptBase(): void
    {
        $trackedKeys = Cache::get(self::TRACKED_KEYS, []);
        
        foreach ($trackedKeys as $key => $timestamp) {
            if ($key !== self::BASE_KEY) {
                Cache::forget($key);
            }
        }
        
        // Clear the tracked keys cache
        Cache::forget(self::TRACKED_KEYS);
        
        // Reinitialize the tracked keys
        Cache::put(self::TRACKED_KEYS, [], self::BASE_TTL);
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

    /**
     * Update specific project cache
     * 
     * @param array $payload
     * 
     * @return void
     */
    public function updateSpecificCache(array $payload): void
    {
        // search project in all caches (base key) with uid
        $projectUid = $payload['uid'] ?? null;
        if (!$projectUid) {
            return;
        }

        $data = Cache::get(self::BASE_KEY, []);

        if (empty($data)) {
            return;
        }

        // update data
        $updatedData = collect($data)->map(function ($project) use ($payload, $projectUid) {
            if ($project['uid'] === $projectUid) {
                // replace all key inside $payload
                foreach ($payload as $key => $value) {
                    $project[$key] = $value;
                }
            }
            return $project;
        })->toArray();

        Cache::put(self::BASE_KEY, $updatedData);
    }
}