<?php

namespace Modules\Company\Services;

use App\Enums\Employee\Status;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Company\Data\Position\PositionSyncData;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Services\GreatdayService;

/**
 * Diff-and-apply sync of Greatday positions into the ERP (position_backups).
 *
 * The flow is intentionally two-step and non-destructive by default:
 *   - preview(): read-only diff (new / changed / gone), never mutates.
 *   - apply():   executes only the actions the user confirmed, inside one
 *                transaction, re-fetching Greatday as the source of truth.
 *
 * Identity is anchored on Greatday's immutable numeric positionId
 * (greatday_position_id), not posCode, because posCode can change in Greatday.
 * Matching falls back to greatday_code for rows not yet linked, so existing
 * data heals itself on the first apply.
 */
class PositionSyncService
{
    /**
     * Settings whose value pins a specific position (stored as position uids).
     * A position referenced here cannot be deleted until it is unpinned.
     *
     * @var array<int, string>
     */
    private const POSITION_PIN_SETTINGS = [
        'position_as_directors',
        'position_as_marcomm',
        'position_as_marketing',
        'position_as_production',
        'position_as_project_manager',
        'position_as_visual_jokey',
        'position_in_interactive_task',
    ];

    public function __construct(private GreatdayService $greatday) {}

    /**
     * Build the read-only diff between Greatday and the ERP. Mutates nothing.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        try {
            $nodes = $this->greatday->getAllPositions();
            $rootId = $this->resolveRootId($nodes);
            $divisionById = $this->indexDivisions($nodes, $rootId);
            $positionNodes = $this->filterPositionNodes($nodes, $rootId);

            $erpPositions = PositionBackup::with('division:id,name')->get();
            $erpById = $erpPositions->keyBy('greatday_position_id');
            $erpByCode = $erpPositions->keyBy('greatday_code');

            $new = [];
            $changed = [];

            foreach ($positionNodes as $node) {
                $erp = $erpById->get($node['positionId']) ?? $erpByCode->get($node['posCode']);
                $divisionNode = $this->resolveDivisionNode($node, $divisionById);

                if (! $erp) {
                    $new[] = [
                        'greatday_position_id' => $node['positionId'],
                        'pos_code' => $node['posCode'],
                        'name' => $node['posNameEn'],
                        'division_name' => $divisionNode['posNameEn'] ?? null,
                    ];

                    continue;
                }

                $changes = $this->detectChanges($erp, $node, $divisionNode);

                if (! empty($changes)) {
                    $changed[] = [
                        'greatday_position_id' => $node['positionId'],
                        'erp_uid' => $erp->uid,
                        'name' => $node['posNameEn'],
                        'changes' => $changes,
                    ];
                }
            }

            $greatdayIds = collect($positionNodes)->pluck('positionId')->all();
            $greatdayCodes = collect($positionNodes)->pluck('posCode')->all();

            $gone = [];
            foreach ($erpPositions as $erp) {
                if ($this->isGreatdayLinked($erp) && ! $this->existsInGreatday($erp, $greatdayIds, $greatdayCodes)) {
                    $references = $this->positionReferences($erp);
                    $gone[] = [
                        'erp_uid' => $erp->uid,
                        'name' => $erp->name,
                        'references' => $references,
                        'deletable' => $references['deletable'],
                    ];
                }
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'new' => $new,
                    'changed' => $changed,
                    'gone' => $gone,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Apply the user-confirmed actions in a single transaction, re-fetching
     * Greatday as the authoritative source. Deletes are guarded (see
     * positionReferences); a delete that is no longer safe is rejected, not forced.
     *
     * @return array<string, mixed>
     */
    public function apply(PositionSyncData $data): array
    {
        DB::beginTransaction();
        try {
            $nodes = $this->greatday->getAllPositions();
            $rootId = $this->resolveRootId($nodes);
            $divisionById = $this->indexDivisions($nodes, $rootId);
            $positionNodes = collect($this->filterPositionNodes($nodes, $rootId))->keyBy('positionId');

            $result = ['created' => [], 'updated' => [], 'deleted' => [], 'rejected' => []];

            foreach ($data->create as $positionId) {
                $this->applyCreate((int) $positionId, $positionNodes, $divisionById, $result);
            }

            foreach ($data->update as $positionId) {
                $this->applyUpdate((int) $positionId, $positionNodes, $divisionById, $result);
            }

            foreach ($data->delete as $uid) {
                $this->applyDelete((string) $uid, $result);
            }

            DB::commit();

            return generalResponse(message: 'Success', data: $result);
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $positionNodes
     * @param  array<int, array<string, mixed>>  $divisionById
     * @param  array<string, mixed>  $result
     */
    private function applyCreate(int $positionId, $positionNodes, array $divisionById, array &$result): void
    {
        $node = $positionNodes->get($positionId);

        if (! $node) {
            $result['rejected'][] = ['greatday_position_id' => $positionId, 'reason' => 'Not found in Greatday'];

            return;
        }

        $division = $this->ensureDivisionForNode($node, $divisionById);

        if (! $division) {
            $result['rejected'][] = ['greatday_position_id' => $positionId, 'reason' => 'Division could not be resolved'];

            return;
        }

        $position = PositionBackup::updateOrCreate(
            ['greatday_position_id' => $node['positionId']],
            [
                'name' => $node['posNameEn'],
                'greatday_code' => $node['posCode'],
                'division_id' => $division->id,
            ]
        );

        $result['created'][] = $position->uid;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $positionNodes
     * @param  array<int, array<string, mixed>>  $divisionById
     * @param  array<string, mixed>  $result
     */
    private function applyUpdate(int $positionId, $positionNodes, array $divisionById, array &$result): void
    {
        $node = $positionNodes->get($positionId);

        if (! $node) {
            $result['rejected'][] = ['greatday_position_id' => $positionId, 'reason' => 'Not found in Greatday'];

            return;
        }

        $position = PositionBackup::where('greatday_position_id', $positionId)->first()
            ?? PositionBackup::where('greatday_code', $node['posCode'])->first();

        if (! $position) {
            $result['rejected'][] = ['greatday_position_id' => $positionId, 'reason' => 'ERP position not found'];

            return;
        }

        $division = $this->ensureDivisionForNode($node, $divisionById);

        $position->update([
            'name' => $node['posNameEn'],
            'greatday_code' => $node['posCode'],
            'greatday_position_id' => $node['positionId'],
            'division_id' => $division?->id ?? $position->division_id,
        ]);

        $result['updated'][] = $position->uid;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function applyDelete(string $uid, array &$result): void
    {
        $position = PositionBackup::where('uid', $uid)->first();

        if (! $position) {
            $result['rejected'][] = ['erp_uid' => $uid, 'reason' => 'Not found'];

            return;
        }

        $references = $this->positionReferences($position);

        if (! $references['deletable']) {
            $result['rejected'][] = [
                'erp_uid' => $uid,
                'reason' => 'Position is still in use',
                'references' => $references,
            ];

            return;
        }

        // soft delete == archive: the row is kept for history and hidden from
        // every normal (non-trashed) query, so it disappears from assignment lists.
        $position->delete();

        $result['deleted'][] = $uid;
    }

    /**
     * DELETE GUARD — a Greatday-linked position may only be archived (soft deleted)
     * when NOTHING in the ERP still points at it. This protects every downstream
     * reference site (employee assignments, Production team selection, signature
     * signatory resolution, dashboards) from dangling position_id values.
     *
     * A position counts as "in use" (deletable = false) when either:
     *   - any non-terminated employee still has employees.position_id = this position, or
     *   - the position uid is pinned in any position_* setting
     *     (directors / marcomm / marketing / production / project_manager /
     *      visual_jokey / interactive_task).
     *
     * When in use, the position stays live and is reported as non-deletable so HR
     * reassigns those references first; apply() never force-deletes it.
     *
     * @return array{deletable: bool, employees: int, settings: array<int, string>}
     */
    protected function positionReferences(PositionBackup $position): array
    {
        $employees = Employee::where('position_id', $position->id)
            ->whereNotIn('status', [Status::Deleted->value, Status::Inactive->value])
            ->count();

        $pinnedIn = [];
        foreach (self::POSITION_PIN_SETTINGS as $key) {
            $value = getSettingByKey($key);

            if (empty($value)) {
                continue;
            }

            $decoded = json_decode($value, true);
            $uids = is_array($decoded) ? $decoded : [$value];

            if (in_array($position->uid, $uids, true)) {
                $pinnedIn[] = $key;
            }
        }

        return [
            'deletable' => $employees === 0 && empty($pinnedIn),
            'employees' => $employees,
            'settings' => $pinnedIn,
        ];
    }

    /**
     * Resolve (or create) the ERP division for a Greatday position node.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $divisionById
     */
    private function ensureDivisionForNode(array $node, array $divisionById): ?DivisionBackup
    {
        $divisionNode = $this->resolveDivisionNode($node, $divisionById);

        if (! $divisionNode) {
            return null;
        }

        $division = DivisionBackup::where('greatday_position_id', $divisionNode['positionId'])->first()
            ?? DivisionBackup::whereNull('greatday_position_id')
                ->whereRaw('LOWER(name) = ?', [strtolower($divisionNode['posNameEn'])])
                ->first();

        if ($division) {
            if (is_null($division->greatday_position_id)) {
                $division->update(['greatday_position_id' => $divisionNode['positionId']]);
            }

            return $division;
        }

        return DivisionBackup::create([
            'name' => $divisionNode['posNameEn'],
            'greatday_position_id' => $divisionNode['positionId'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $erp
     */
    private function detectChanges(PositionBackup $erp, array $node, ?array $divisionNode): array
    {
        $changes = [];

        if ($erp->name !== $node['posNameEn']) {
            $changes['name'] = ['from' => $erp->name, 'to' => $node['posNameEn']];
        }

        if ($erp->greatday_code !== $node['posCode']) {
            $changes['pos_code'] = ['from' => $erp->greatday_code, 'to' => $node['posCode']];
        }

        $divisionName = $divisionNode['posNameEn'] ?? null;
        if ($divisionName && $erp->division?->name !== $divisionName) {
            $changes['division'] = ['from' => $erp->division?->name, 'to' => $divisionName];
        }

        return $changes;
    }

    private function isGreatdayLinked(PositionBackup $position): bool
    {
        return ! is_null($position->greatday_position_id) || ! is_null($position->greatday_code);
    }

    /**
     * @param  array<int, int>  $greatdayIds
     * @param  array<int, string>  $greatdayCodes
     */
    private function existsInGreatday(PositionBackup $position, array $greatdayIds, array $greatdayCodes): bool
    {
        if (! is_null($position->greatday_position_id) && in_array($position->greatday_position_id, $greatdayIds, false)) {
            return true;
        }

        return ! is_null($position->greatday_code) && in_array($position->greatday_code, $greatdayCodes, true);
    }

    /**
     * The company root is the node with parentId == 0.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function resolveRootId(array $nodes): int
    {
        $root = collect($nodes)->firstWhere('parentId', 0);

        if (! $root) {
            throw new \RuntimeException('Greatday position tree has no root (parentId = 0) node.');
        }

        return (int) $root['positionId'];
    }

    /**
     * Divisions are the direct children of the company root.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>> Keyed by positionId
     */
    private function indexDivisions(array $nodes, int $rootId): array
    {
        return collect($nodes)
            ->where('parentId', $rootId)
            ->keyBy('positionId')
            ->all();
    }

    /**
     * Positions are every node that is neither the root nor a division.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function filterPositionNodes(array $nodes, int $rootId): array
    {
        return collect($nodes)
            ->filter(fn ($node) => (int) $node['parentId'] !== 0 && (int) $node['parentId'] !== $rootId)
            ->values()
            ->all();
    }

    /**
     * Resolve the division node a position belongs to by walking its parentPath
     * ancestors (nearest first) until a division node is found.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $divisionById
     * @return array<string, mixed>|null
     */
    private function resolveDivisionNode(array $node, array $divisionById): ?array
    {
        if (isset($divisionById[$node['parentId']])) {
            return $divisionById[$node['parentId']];
        }

        $ancestors = array_reverse(explode(',', (string) ($node['parentPath'] ?? '')));

        foreach ($ancestors as $ancestorId) {
            $ancestorId = (int) $ancestorId;
            if (isset($divisionById[$ancestorId])) {
                return $divisionById[$ancestorId];
            }
        }

        return null;
    }
}
