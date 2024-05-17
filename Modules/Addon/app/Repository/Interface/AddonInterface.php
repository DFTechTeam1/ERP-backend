<?php

namespace Modules\Addon\Repository\Interface;

abstract class AddonInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = [], bool $isDistinct = false);

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page);

    abstract function show(int $id, string $select = '*', array $relation = []);

    abstract function store(array $data);

    abstract function update(array $data, string $id = '', string $where = '');

    abstract function delete(int $id);

    abstract function bulkDelete(array $ids, string $key = '');
}