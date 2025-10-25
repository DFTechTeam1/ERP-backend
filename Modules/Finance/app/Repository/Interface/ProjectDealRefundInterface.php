<?php

namespace Modules\Finance\Repository\Interface;

abstract class ProjectDealRefundInterface {
    abstract function list(string $select = '*', string $where = "", array $relation = []);

    abstract public function pagination(string $select, string $where, array $relation, int $itemsPerPage, int $page);

    abstract function show(string $uid, string $select = '*', array $relation = []);

    abstract function store(array $data);

    abstract function update(array $data, string $id = '', string $where = '');

    abstract function delete(int $id);

    abstract function bulkDelete(array $ids, string $key = '');
}