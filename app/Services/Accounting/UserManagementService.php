<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserManagementService
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): User
    {
        return DB::transaction(function () use ($payload): User {
            $user = $this->userRepository->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => $payload['password'],
                'role' => $payload['role'],
                'branch_id' => $payload['branch_id'] ?? null,
            ]);

            $user->syncRoles([$payload['role']]);

            return $user->refresh();
        });
    }
}
