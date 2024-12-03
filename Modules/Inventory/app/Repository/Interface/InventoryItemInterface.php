<?php

namespace Modules\Inventory\Repository\Interface;

abstract class InventoryItemInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = []);

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page);

    abstract function show(string $uid, string $select = '*', array $relation = [], string $where = '');

    abstract function store(array $data);

    abstract function update(array $data, string $id = '', string $where = '');

    abstract function delete(int $id, string $key);

    abstract function upsert(array $data, array $uniqueColumns, array $updatedColumns);

    abstract function bulkDelete(array $ids, string $key = '');
}
