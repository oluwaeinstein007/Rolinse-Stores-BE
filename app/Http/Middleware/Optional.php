<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Optional
{
    protected AuthFactory $auth;

    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if Authorization header exists
        $header = $request->header('Authorization');

        if ($header) {
            // Attempt to authenticate the user using the sanctum guard
            $user = $this->auth->guard('sanctum')->user();

            if ($user) {
                // Attach the authenticated user to both attributes and input
                $request->attributes->set('authUser', $user);
                $request->merge(['authUser' => $user]);
                $request->attributes->set('user_is_authenticated', true);
            } else {
                $request->attributes->set('user_is_authenticated', false);
            }
        } else {
            // If no Authorization header is present, set unauthenticated flag
            $request->attributes->set('user_is_authenticated', false);
        }

        return $next($request);
    }
}
