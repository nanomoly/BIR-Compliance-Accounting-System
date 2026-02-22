<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccountRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): Account;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Account;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Account $account, array $payload): Account;

    public function delete(Account $account): void;
}
