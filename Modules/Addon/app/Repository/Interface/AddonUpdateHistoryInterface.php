<?php

namespace Modules\Addon\Repository\Interface;

abstract class AddonUpdateHistoryInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = [], string $limit = '', bool $isDistinct = false);

    abstract public function pagination(string $select, string $where, array $relation, int $itemsPerPage, int $page);

    abstract public function show(string $uid, string $select = '*', array $relation = []);

    abstract public function store(array $data);

    abstract public function update(array $data, string $id = '', string $where = '');

    abstract public function delete(int $id);

    abstract public function bulkDelete(array $ids, string $key = '');
}
