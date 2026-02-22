<?php

namespace App\Repositories\Eloquent;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountRepository implements AccountRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Account::with('children')->orderBy('code')->paginate($perPage);
    }

    public function findById(int $id): Account
    {
        return Account::query()->findOrFail($id);
    }

    public function create(array $payload): Account
    {
        return Account::query()->create($payload);
    }

    public function update(Account $account, array $payload): Account
    {
        $account->fill($payload)->save();

        return $account->refresh();
    }

    public function delete(Account $account): void
    {
        $account->delete();
    }
}
