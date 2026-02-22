<?php

namespace App\Policies;

use App\Enums\JournalStatus;
use App\Enums\UserRole;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('journals.view')
            || in_array($user->role, [UserRole::ADMIN, UserRole::ACCOUNTANT, UserRole::AUDITOR], true);
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('journals.create')
            || in_array($user->role, [UserRole::ADMIN, UserRole::ACCOUNTANT], true);
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        return ($user->can('journals.post')
            || in_array($user->role, [UserRole::ADMIN, UserRole::ACCOUNTANT], true))
            && $journalEntry->status === JournalStatus::DRAFT;
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return ($user->can('journals.reverse')
            || in_array($user->role, [UserRole::ADMIN, UserRole::ACCOUNTANT], true))
            && $journalEntry->status === JournalStatus::POSTED;
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $user->role === UserRole::ADMIN && $journalEntry->status !== JournalStatus::POSTED;
    }
}
