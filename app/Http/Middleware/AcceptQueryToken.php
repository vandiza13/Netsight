<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AcceptQueryToken
{
    /**
     * Handle an incoming request.
     *
     * Jika request memiliki parameter query 'token' dan tidak memiliki
     * header Authorization, set header Authorization dari token tersebut.
     * Digunakan terutama untuk SSE (EventSource) yang tidak bisa mengirim header custom.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->has('token') && !$request->headers->has('Authorization')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->query('token'));
        }

        return $next($request);
    }
}
