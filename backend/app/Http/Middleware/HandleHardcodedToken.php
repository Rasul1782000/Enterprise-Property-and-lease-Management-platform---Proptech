<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleHardcodedToken
{
    public function handle(Request $request, Closure $next): Response
    {

        if (Auth::guard('sanctum')->check()) {
            return $next($request);
        }

        $token = $request->bearerToken();

        if (str_starts_with($token, 'hardcoded-admin-token-')) {
            $user = [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@propertylease.test',
                'role' => 'admin',
                'tenant' => null,
                'managedProperties' => [],
            ];

            $request->setUserResolver(fn () => (object) $user);
            $request->merge(['hardcoded_user' => $user]);
        }

        return $next($request);
    }
}
