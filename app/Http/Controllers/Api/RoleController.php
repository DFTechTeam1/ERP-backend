<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\Create;
use App\Http\Requests\Roles\Update;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new RoleService;
    }

    /**
     * GET /api/roles — paginated, searchable list of roles for the datatable.
     *
     * Query params (read from the request by the service): `limit`, `page`,
     * `name`, `sortBy`.
     */
    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * GET /api/roles/getAll — every role as a select option (`{value, title, name}`).
     */
    public function getAll(): JsonResponse
    {
        return apiResponse($this->service->getAll());
    }

    /**
     * POST /api/roles — create a role with its permissions.
     */
    public function store(Create $request): JsonResponse
    {
        return apiResponse($this->service->store($request->validated()));
    }

    /**
     * GET /api/roles/{id} — role detail with its permissions.
     */
    public function show(string $id): JsonResponse
    {
        return apiResponse($this->service->show((int) $id));
    }

    /**
     * PUT/PATCH /api/roles/{id} — replace a role's permission set.
     */
    public function update(Update $request, string $id): JsonResponse
    {
        return apiResponse($this->service->update($request->validated(), (int) $id));
    }

    /**
     * DELETE /api/roles/{id} — delete a role (blocked while users still hold it).
     */
    public function destroy(string $id): JsonResponse
    {
        return apiResponse($this->service->destroy((int) $id));
    }

    /**
     * POST /api/roles/bulk — delete several roles at once.
     *
     * Expects `ids` as a list of `{uid: <roleId>}` objects; the role ids are
     * unwrapped before being handed to the service.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }
}
