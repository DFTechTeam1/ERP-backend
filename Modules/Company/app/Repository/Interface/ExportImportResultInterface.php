<?php

namespace Modules\Company\Repository\Interface;

abstract class ExportImportResultInterface
{
    abstract public function list(string $select = '*', string $where = '', array $relation = []);

    abstract public function pagination(int $itemsPerPage, int $page, string $select = '*', string $where = '', array $relation = [], string $orderBy = '');

    abstract public function show(string $uid, string $select = '*', array $relation = []);

    abstract public function store(array $data);

    abstract public function update(array $data, string $id = '', string $where = '');

    abstract public function delete(int $id);

    abstract public function bulkDelete(array $ids, string $key = '');
}
