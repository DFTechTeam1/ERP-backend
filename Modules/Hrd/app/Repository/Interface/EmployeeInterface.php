<?php

namespace Modules\Hrd\Repository\Interface;

abstract class EmployeeInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = [], string $orderBy = '', string $limit = '', array $whereHas = [], array $whereIn = []);

    abstract public function pagination(string $select, string $where, array $relation, int $itemsPerPage, int $page, array $whereHas = [],
        string $orderBy = '');

    abstract public function show(string $uid, string $select = '*', array $relation = [], string $where = '');

    abstract public function store(array $data);

    abstract public function update(array $data, string $uid);

    abstract public function delete(string $uid);

    abstract public function bulkDelete(array $ids, string $key = '');
}
