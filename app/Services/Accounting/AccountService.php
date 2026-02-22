<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountService
{
    public function __construct(private readonly AccountRepositoryInterface $accountRepository) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->accountRepository->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Account
    {
        return $this->accountRepository->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Account $account, array $payload): Account
    {
        return $this->accountRepository->update($account, $payload);
    }

    public function delete(Account $account): void
    {
        $this->accountRepository->delete($account);
    }
}
