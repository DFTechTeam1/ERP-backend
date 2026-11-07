<?php

namespace Modules\Company\Repository\Interface;

use Modules\Company\Models\NotificationSetting;

abstract class NotificationSettingInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = []): mixed;

    abstract public function pagination(string $select, string $where, int $itemsPerPage, int $page, string $orderBy = ''): mixed;

    abstract public function count(string $where = ''): int;

    abstract public function show(int $id, string $select = '*'): ?NotificationSetting;

    abstract public function store(array $data): NotificationSetting;

    abstract public function update(array $data, int $id): ?NotificationSetting;

    abstract public function delete(int $id): bool;
}
