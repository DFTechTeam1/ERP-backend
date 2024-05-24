<?php

namespace Modules\Hrd\Repository\Interface;

abstract class EmployeeInterface
{
    abstract function list(string $select = '*', string $where = "", array $relation = [], string $orderBy = '', string $limit = '', array $whereHas = [], array $whereIn = []);

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page, string $orderBy = "");

    abstract function show(string $uid, string $select = '*', array $relation = []);

    abstract function store(array $data);

    abstract function update(array $data, string $uid);

    abstract function delete(string $uid);

    abstract function bulkDelete(array $ids, string $key = '');
}
