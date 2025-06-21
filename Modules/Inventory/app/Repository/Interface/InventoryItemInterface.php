<?php

namespace Modules\Inventory\Repository\Interface;

abstract class InventoryItemInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = []);

    abstract public function pagination(string $select, string $where, array $relation, int $itemsPerPage, int $page);

    abstract public function show(string $uid, string $select = '*', array $relation = [], string $where = '');

    abstract public function store(array $data);

    abstract public function update(array $data, string $id = '', string $where = '');

    abstract public function delete(int $id, string $key);

    abstract public function upsert(array $data, array $uniqueColumns, array $updatedColumns);

    abstract public function bulkDelete(array $ids, string $key = '');
}
