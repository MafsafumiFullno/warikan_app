<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\Exceptions\MissingAbilityException;

class AuthenticateCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->user()) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // 顧客として認証されているかチェック
        if (!$request->user() instanceof \App\Models\Customer) {
            throw new AuthenticationException('Invalid user type.');
        }

        return $next($request);
    }
}

