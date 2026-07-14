<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit Log Middleware — Mencatat setiap aksi API.
 *
 * Format: [Timestamp] - [User] - [Aksi] - [Target] - [Router]
 *
 * @see SECURITY.md Section 6
 */
class AuditLogMiddleware
{
    /**
     * Aksi-aksi yang wajib dicatat ke audit log.
     */
    private const AUDITABLE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hanya catat aksi yang mengubah state, atau GET pada endpoint sensitif
        if ($this->shouldAudit($request, $response)) {
            $this->recordAudit($request, $response);
        }

        return $response;
    }

    private function shouldAudit(Request $request, Response $response): bool
    {
        // Selalu catat POST/PUT/PATCH/DELETE
        if (in_array($request->method(), self::AUDITABLE_METHODS)) {
            return true;
        }

        // Catat GET pada endpoint sensitif (torch stream, health-check)
        $sensitivePatterns = [
            'torch/*/stream',
            'routers/*/health-check',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if ($request->is('api/' . $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function recordAudit(Request $request, Response $response): void
    {
        $user = $request->user();

        if (! $user) {
            return;
        }

        // Ekstrak informasi dari request
        $action = $request->method() . ' ' . $request->path();
        $targetUsername = $request->input('username')
            ?? $request->route('username')
            ?? null;

        $routerId = null;
        $deletedRouterId = null;

        if ($request->is('api/routers/*')) {
            $idVal = $request->route('id') ?? $request->route('router');
            if ($request->method() === 'DELETE') {
                $deletedRouterId = $idVal;
            } else {
                $routerId = $idVal;
            }
        } else {
            $routerId = $request->input('router_id');
        }

        $metadata = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'response_status' => $response->getStatusCode(),
            'request_params' => $this->sanitizeParams($request->all()),
        ];

        if ($deletedRouterId) {
            $metadata['deleted_router_id'] = (int) $deletedRouterId;
        }

        AuditLog::record(
            staffId: $user->id,
            action: $action,
            targetUsername: $targetUsername,
            routerId: is_numeric($routerId) ? (int) $routerId : null,
            metadata: $metadata
        );
    }

    /**
     * Sanitize params untuk logging — hapus data sensitif.
     */
    private function sanitizeParams(array $params): array
    {
        $redacted = ['password', 'totp_code', 'credential', 'secret'];

        foreach ($redacted as $key) {
            if (isset($params[$key])) {
                $params[$key] = '[REDACTED]';
            }
        }

        return $params;
    }
}
