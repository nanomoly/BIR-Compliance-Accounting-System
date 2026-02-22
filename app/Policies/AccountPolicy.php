<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounts.view')
            || in_array($user->role, [UserRole::ADMIN, UserRole::ACCOUNTANT, UserRole::AUDITOR], true);
    }

    public function view(User $user, Account $account): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('accounts.create')
            || in_array($user->role, [UserRole::ADMIN, UserRole::ACCOUNTANT], true);
    }

    public function update(User $user, Account $account): bool
    {
        return $user->can('accounts.update')
            || in_array($user->role, [UserRole::ADMIN, UserRole::ACCOUNTANT], true);
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->can('accounts.delete') || $user->role === UserRole::ADMIN;
    }
}
