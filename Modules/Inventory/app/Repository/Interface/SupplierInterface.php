<?php

namespace Modules\Inventory\Repository\Interface;

abstract class SupplierInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = []);

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page);

    abstract function show(string $uid, string $select = '*', array $relation = []);

    abstract function store(array $data);

    abstract function update(array $data, string $id);

    abstract function delete(int $id);

    abstract function bulkDelete(array $ids, string $key = '');
}