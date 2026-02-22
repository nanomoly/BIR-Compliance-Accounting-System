<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UserLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit_trail.view'), 403);

        $query = AuditLog::query()->with('user')->latest('occurred_at');

        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('occurred_at', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('occurred_at', '<=', $request->string('to_date')->toString());
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 25)));
    }

    public function activities(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit_trail.view'), 403);

        $query = UserLog::query()->with('user')->latest('occurred_at');

        if ($request->filled('activity')) {
            $query->where('activity', $request->string('activity')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('occurred_at', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('occurred_at', '<=', $request->string('to_date')->toString());
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 25)));
    }
}
