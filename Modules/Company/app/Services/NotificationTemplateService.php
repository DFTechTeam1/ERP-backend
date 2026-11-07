<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use App\Services\NotificationService;
use Modules\Company\Repository\NotificationSettingRepository;

/**
 * CRUD + helper operations for notification templates (the notification_settings table).
 *
 * Templates are keyed by `action`, the string senders pass to NotificationService::send().
 * Responses follow the house convention: snake_case keys inside the generalResponse envelope.
 */
class NotificationTemplateService
{
    private const SELECT = 'id,action,template,template_html,notification_channel,created_at,updated_at';

    /**
     * Columns that may be used for sorting (whitelisted because the order clause is raw).
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['id', 'action', 'created_at', 'updated_at'];

    public function __construct(private NotificationSettingRepository $repo) {}

    /**
     * Paginated list for the datatable: { paginated: [...], totalData: int }.
     */
    public function list(): array
    {
        try {
            $itemsPerPage = (int) (request('itemsPerPage') ?? 15);
            $page = (int) (request('page') ?? 1);
            $offset = $page > 1 ? ($page * $itemsPerPage) - $itemsPerPage : 0;

            $where = $this->buildWhere();
            $sort = $this->buildSort();

            $paginated = $this->repo->pagination(self::SELECT, $where, $itemsPerPage, $offset, $sort);
            $totalData = $this->repo->count($where);

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated->toArray(),
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Single template by id.
     */
    public function show(int $id): array
    {
        try {
            $data = $this->repo->show($id, self::SELECT);

            if (! $data) {
                return $this->notFound();
            }

            return generalResponse('Success', false, $data->toArray());
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Create a template. $data is already snake_case from the Create request.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): array
    {
        try {
            $created = $this->repo->store($data);

            return generalResponse(
                'Notification template created.',
                false,
                $this->repo->show($created->id, self::SELECT)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update a template. $data never contains `action` (immutable; see Update request).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, int $id): array
    {
        try {
            $updated = $this->repo->update($data, $id);

            if (! $updated) {
                return $this->notFound();
            }

            return generalResponse(
                'Notification template updated.',
                false,
                $this->repo->show($id, self::SELECT)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete a template by id.
     */
    public function delete(int $id): array
    {
        try {
            if (! $this->repo->show($id, 'id')) {
                return $this->notFound();
            }

            $this->repo->delete($id);

            return generalResponse('Notification template deleted.', false, []);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Reference data for the template editor (pickers + placeholder palette).
     */
    public function metadata(): array
    {
        return generalResponse('Success', false, [
            'channels' => NotificationService::ALL_CHANNELS,
            'placeholders' => [
                ['token' => '<parameterN>', 'html_token' => '&lt;parameterN&gt;', 'type' => 'text', 'repeatable' => true],
                ['token' => '<imageN>', 'html_token' => '&lt;imageN&gt;', 'type' => 'image', 'repeatable' => true],
                ['token' => '<audioN>', 'html_token' => '&lt;audioN&gt;', 'type' => 'audio', 'repeatable' => true],
                ['token' => '<documentN>', 'html_token' => '&lt;documentN&gt;', 'type' => 'document', 'repeatable' => true],
                ['token' => '<bubble>', 'html_token' => '&lt;bubble&gt;', 'type' => 'newline', 'repeatable' => true],
            ],
        ]);
    }

    /**
     * Render a draft template with sample data without persisting it.
     *
     * @param  array<string, mixed>  $data
     */
    public function preview(string $template, ?string $templateHtml, array $data): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                NotificationService::preview($template, $templateHtml, $data),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build the raw WHERE clause from the request filters. All interpolated values are
     * either whitelisted or single-quote escaped because the repository runs it raw.
     */
    private function buildWhere(): string
    {
        $conditions = [];

        $search = request('search');
        if (is_string($search) && $search !== '') {
            $safe = $this->escape($search);
            $conditions[] = "(action LIKE '%{$safe}%' OR template LIKE '%{$safe}%')";
        }

        $channel = request('channel');
        if (is_string($channel) && in_array($channel, NotificationService::ALL_CHANNELS, true)) {
            $conditions[] = "JSON_CONTAINS(notification_channel, '\"{$channel}\"')";
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Build the raw ORDER BY from the request `sort` param (array of {field, order}),
     * falling back to newest-updated first. Fields are whitelisted; order is asc/desc.
     */
    private function buildSort(): string
    {
        $sort = request('sort');

        if (! is_array($sort) || empty($sort)) {
            return 'updated_at desc';
        }

        $clauses = [];
        foreach ($sort as $item) {
            $field = $item['field'] ?? null;
            $order = strtolower($item['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if (in_array($field, self::SORTABLE, true)) {
                $clauses[] = "{$field} {$order}";
            }
        }

        return empty($clauses) ? 'updated_at desc' : implode(', ', $clauses);
    }

    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function notFound(): array
    {
        return generalResponse('Notification template not found.', true, [], Code::NotFound->value);
    }
}
