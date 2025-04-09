<?php

namespace Modules\Company\Repository\Interface;

abstract class PositionInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = []);

    abstract function pagination(string $select = '*', string $where = "", array $relation = [], int $itemsPerPage, int $page, array $whereHas = [],
    string $orderBy = '');

    abstract function show(string $uid, string $select = '*', array $relation = [], string $where = '');

    abstract function store(array $data);

    abstract function update(array $data, string $uid);

    abstract function delete(string $uid);

    abstract function bulkDelete(array $uids, string $key = '');
}
