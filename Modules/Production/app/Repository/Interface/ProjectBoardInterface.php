<?php

namespace Modules\Production\Repository\Interface;

abstract class ProjectBoardInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = []);

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page);

    abstract function show(int $uid, string $select = '*', array $relation = [], string $where = '');

    abstract function store(array $data);

    abstract function update(array $data, string $id = '', string $where = '');

    abstract function delete(int $id = 0, string $where = '');

    abstract function bulkDelete(array $ids, string $key = '');
}