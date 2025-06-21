<?php

namespace Modules\Production\Repository\Interface;

abstract class ProjectTaskPicInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = [], string $orderBy = '', int $limit = 0);

    abstract public function pagination(string $select, string $where, array $relation, int $itemsPerPage, int $page);

    abstract public function show(int $id, string $select = '*', array $relation = []);

    abstract public function store(array $data);

    abstract public function update(array $data, string $id = '', string $where = '');

    abstract public function delete(int $id = 0, string $where = '');

    abstract public function bulkDelete(array $ids, string $key = '');

    abstract public function deleteWithCondition(string $where);

    abstract public function upsert(array $data, array $unique, array $updatedColumn);
}
