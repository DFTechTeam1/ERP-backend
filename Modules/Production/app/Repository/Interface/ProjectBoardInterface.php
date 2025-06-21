<?php

namespace Modules\Production\Repository\Interface;

abstract class ProjectBoardInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = []);

    abstract public function pagination(string $select, string $where, array $relation, int $itemsPerPage, int $page);

    abstract public function show(int $uid, string $select = '*', array $relation = [], string $where = '');

    abstract public function store(array $data);

    abstract public function update(array $data, string $id = '', string $where = '');

    abstract public function delete(int $id = 0, string $where = '');

    abstract public function bulkDelete(array $ids, string $key = '');
}
