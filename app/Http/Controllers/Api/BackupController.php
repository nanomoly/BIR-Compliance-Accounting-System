<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\Accounting\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backupService) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('backups.view'), 403);

        return response()->json(Backup::query()->latest('backup_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('backups.create'), 403);

        $backup = $this->backupService->create((int) $request->user()->id);

        return response()->json($backup, 201);
    }

    public function restore(Request $request, int $backupId): JsonResponse
    {
        abort_unless($request->user()?->can('backups.restore'), 403);

        return response()->json($this->backupService->restore($backupId));
    }
}
