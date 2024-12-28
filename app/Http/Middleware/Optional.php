<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class Optional
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is authenticated
        if (Auth::check()) {
            // Attach authenticated user to the request
            $request->merge(['authUser' => Auth::user()]);
            $request->attributes->set('user_is_authenticated', true);
        } else {
            // Set guest flag for unauthenticated users
            $request->attributes->set('user_is_authenticated', false);
        }

        return $next($request);
    }
}
