<?php

namespace Modules\Inventory\Repository\Interface;

abstract class SupplierInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = []);

    abstract public function pagination(string $select, string $where, array $relation, int $itemsPerPage, int $page);

    abstract public function show(string $uid, string $select = '*', array $relation = []);

    abstract public function store(array $data);

    abstract public function update(array $data, string $id);

    abstract public function delete(int $id);

    abstract public function bulkDelete(array $ids, string $key = '');
}
