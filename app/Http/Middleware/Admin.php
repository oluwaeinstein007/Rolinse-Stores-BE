<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !($request->user()->user_role_id === 1)) {
            return response()->json(['message' => 'Unauthorized: Only admin can enter allowed routes.'], 403);
        }

        if ($request->user()->is_suspended) {
            return response()->json(['message' => 'Unauthorized: Your account has been suspended.'], 403);
        }

        return $next($request);
    }
}
