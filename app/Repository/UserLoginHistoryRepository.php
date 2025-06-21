<?php

namespace App\Repository;

use App\Models\UserLoginHistory;

class UserLoginHistoryRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new UserLoginHistory;
    }

    public function store(array $data)
    {
        return $this->model->create($data);
    }
}
