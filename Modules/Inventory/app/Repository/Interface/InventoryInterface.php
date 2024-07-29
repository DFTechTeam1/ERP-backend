<?php

namespace Modules\Inventory\Repository\Interface;

abstract class InventoryInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = [], string $orderBy = '');

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page, array $whereHas = []);

    abstract function show(string $uid, string $select = '*', array $relation = []);

    abstract function store(array $data);

    abstract function update(array $data, string $id = '', string $where = '');

    abstract function delete(int $id);

    abstract function bulkDelete(array $ids, string $key = '');
}