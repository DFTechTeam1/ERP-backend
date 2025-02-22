<?php

namespace Modules\Production\Repository\Interface;

abstract class ProjectInterface {
    abstract function list(
        string $select = '*',
        string $where = "",
        array $relation = [],
        array $whereHas = [],
        string $orderBy = '',
        int $limit = 0,
        array $isGetDistance = [],
        array $has = []
    );

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage = 10, int $page = 1, array $has = []);

    abstract function show(string $uid, string $select = '*', array $relation = [], string $where = '');

    abstract function store(array $data);

    abstract function update(array $data, string $id = '', string $where = '');

    abstract function delete(int $id);

    abstract function bulkDelete(array $ids, string $key = '');
}