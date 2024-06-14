<?php

namespace Modules\Production\Repository\Interface;

abstract class ProjectTaskPicInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = []);

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page);

    abstract function show(int $id, string $select = '*', array $relation = []);

    abstract function store(array $data);

    abstract function update(array $data, string $id = '', string $where = '');

    abstract function delete(int $id = 0, string $where = '');

    abstract function bulkDelete(array $ids, string $key = '');

    abstract function deleteWithCondition(string $where);

    abstract function upsert(array $data, array $unique, array $updatedColumn);
}