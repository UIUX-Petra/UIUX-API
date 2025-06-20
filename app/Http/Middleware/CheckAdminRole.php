<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::guard('admin')->check()) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }
        $admin = Auth::guard('admin')->user();
        foreach ($roles as $role) {
            if ($admin->tokenCan($role)) {
                return $next($request);
            }
        }
        
        return response()->json(
            ['message' => 'You do not have the required role to perform this action.'],
            Response::HTTP_FORBIDDEN
        );
    }
}
