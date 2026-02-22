<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBranchRequest;
use App\Http\Requests\Api\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('branches.view'), 403);

        $branches = Branch::query()
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($branches);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $companyProfile = CompanyProfile::query()->first();

        if (! $companyProfile) {
            return response()->json([
                'message' => 'Company profile is required before creating branches.',
            ], 422);
        }

        $payload = $request->validated();

        if (($payload['is_main'] ?? false) === true) {
            Branch::query()->where('is_main', true)->update(['is_main' => false]);
        }

        $branch = Branch::query()->create([
            'company_profile_id' => $companyProfile->id,
            'code' => $payload['code'],
            'name' => $payload['name'],
            'tin' => $payload['tin'] ?? null,
            'address' => $payload['address'],
            'is_main' => (bool) ($payload['is_main'] ?? false),
        ]);

        return response()->json($branch, 201);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $payload = $request->validated();

        if (($payload['is_main'] ?? false) === true) {
            Branch::query()->where('id', '!=', $branch->id)->where('is_main', true)->update(['is_main' => false]);
        }

        $branch->update([
            'code' => $payload['code'],
            'name' => $payload['name'],
            'tin' => $payload['tin'] ?? null,
            'address' => $payload['address'],
            'is_main' => (bool) ($payload['is_main'] ?? false),
        ]);

        return response()->json($branch->refresh());
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()?->can('branches.delete'), 403);

        if ($branch->is_main) {
            return response()->json([
                'message' => 'Main branch cannot be deleted.',
            ], 422);
        }

        $branch->delete();

        return response()->json(status: 204);
    }
}
