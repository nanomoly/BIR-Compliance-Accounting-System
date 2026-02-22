<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()->with(['branch', 'roles', 'permissions'])->latest('id')->paginate($perPage);
    }

    public function create(array $payload): User
    {
        return User::query()->create($payload);
    }
}
