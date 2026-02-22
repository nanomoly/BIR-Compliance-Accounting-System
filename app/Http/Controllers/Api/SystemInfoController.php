<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemInfoController extends Controller
{
    public function show(): JsonResponse
    {
        abort_unless(auth()->user()?->can('system_info.view'), 403);

        $company = CompanyProfile::query()->first();

        return response()->json([
            'software_version' => $company?->software_version ?? config('app.version', '1.0.0'),
            'database_version' => $company?->database_version ?? $this->resolveDatabaseVersion(),
            'developer_information' => [
                'name' => $company?->developer_name,
                'tin' => $company?->developer_tin,
            ],
            'company' => [
                'name' => $company?->name,
                'tin' => $company?->tin,
                'address' => $company?->registered_address,
            ],
        ]);
    }

    public function updateCompanyProfile(UpdateCompanyProfileRequest $request): JsonResponse
    {
        $company = CompanyProfile::query()->first();

        if (! $company) {
            $company = CompanyProfile::query()->create([
                'name' => $request->string('name')->toString(),
                'tin' => $request->string('tin')->toString(),
                'registered_address' => $request->string('registered_address')->toString(),
                'software_version' => config('app.version', '1.0.0'),
                'database_version' => $this->resolveDatabaseVersion(),
                'developer_name' => 'Standard CAS Team',
                'developer_tin' => null,
            ]);
        }

        $company->fill([
            'name' => $request->string('name')->toString(),
            'tin' => $request->string('tin')->toString(),
            'registered_address' => $request->string('registered_address')->toString(),
        ]);
        $company->save();

        return response()->json([
            'message' => 'Company profile updated successfully.',
            'company' => [
                'name' => $company->name,
                'tin' => $company->tin,
                'address' => $company->registered_address,
            ],
        ]);
    }

    private function resolveDatabaseVersion(): string
    {
        try {
            if (DB::getDriverName() === 'sqlite') {
                return 'SQLite';
            }

            return (string) (DB::selectOne('select version() as version')->version ?? 'Unknown');
        } catch (Throwable) {
            return 'Unknown';
        }
    }
}
