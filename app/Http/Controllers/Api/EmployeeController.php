<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEmployeeRequest;
use App\Http\Requests\Api\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('hr.view'), 403);

        $employees = Employee::query()
            ->with('branch:id,name,code')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::query()->create($request->validated());

        return response()->json($employee->load('branch:id,name,code'), 201);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return response()->json($employee->refresh()->load('branch:id,name,code'));
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        abort_unless($request->user()?->can('hr.delete'), 403);

        $employee->delete();

        return response()->json(status: 204);
    }
}
