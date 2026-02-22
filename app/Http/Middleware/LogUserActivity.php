<?php

namespace App\Http\Middleware;

use App\Models\UserLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            UserLog::query()->create([
                'user_id' => $request->user()->id,
                'activity' => 'api_request',
                'route' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'occurred_at' => now(),
            ]);
        }

        return $response;
    }
}
