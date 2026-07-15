<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPostgresSchema
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $schema = $request->header('X-Demo-Schema') ?: $request->query('schema');

        if ($schema && preg_match('/^demo_[a-zA-Z0-9_]+$/', $schema)) {
            \Illuminate\Support\Facades\DB::statement("SET search_path TO {$schema}, public");
        } else {
            \Illuminate\Support\Facades\DB::statement("SET search_path TO public");
        }

        return $next($request);
    }
}
