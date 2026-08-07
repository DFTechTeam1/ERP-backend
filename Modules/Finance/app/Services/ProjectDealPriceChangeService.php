<?php

namespace Modules\Finance\Services;

use Modules\Finance\Repository\ProjectDealPriceChangeRepository;

class ProjectDealPriceChangeService
{
    /**
     * Construction Data
     */
    public function __construct(
        private readonly ProjectDealPriceChangeRepository $repo
    ) {}

    /**
     * Get list of data
     *
     * @param  array<string,mixed>  $params  See BaseRepository for the supported keys
     */
    public function list(array $params = []): array
    {
        try {
            $itemsPerPage = (int) (request('itemsPerPage') ?? config('app.pagination_length'));

            $paginated = $this->repo->paginate($params, $itemsPerPage);

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated->items(),
                    'totalData' => $paginated->total(),
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show([
                'where' => ['id' => $uid],
            ]);

            if (! $data) {
                return errorResponse(message: __('notification.dataNotFound'));
            }

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
     *
     * @param  array<string,mixed>  $data
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param  array<string,mixed>  $data
     */
    public function update(array $data, string $id): array
    {
        try {
            $model = $this->repo->show([
                'where' => ['id' => $id],
            ]);

            if (! $model) {
                return errorResponse(message: __('notification.dataNotFound'));
            }

            $this->repo->update($model, $data);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     */
    public function delete(int $id): array
    {
        try {
            $model = $this->repo->show([
                'where' => ['id' => $id],
            ]);

            if (! $model) {
                return errorResponse(message: __('notification.dataNotFound'));
            }

            $this->repo->delete($model);

            return generalResponse(
                'Success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     *
     * @param  array<int,int|string>  $ids
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $this->repo->get([
                'whereIn' => ['id' => $ids],
            ])->each(function ($model) {
                $this->repo->delete($model);
            });

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
