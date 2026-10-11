<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            in_array($request->user()?->role, ['admin', 'manager', 'accountant'], true),
            403,
            'This action is only available to staff.'
        );

        return $next($request);
    }
}
