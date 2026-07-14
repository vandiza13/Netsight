<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AuditLogController — Akses log audit.
 *
 * Log audit adalah append-only dan TIDAK BOLEH dihapus/diedit.
 *
 * @see SECURITY.md Section 6
 * @role ADMIN only
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs — Daftar log audit dengan filter.
     *
     * @role ADMIN
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with(['staff:id,name,email,role', 'router:id,name']);

        // Filter berdasarkan staff
        if ($staffId = $request->query('staff_id')) {
            $query->where('staff_noc_id', $staffId);
        }

        // Filter berdasarkan action
        if ($action = $request->query('action')) {
            $query->where('action', 'LIKE', "%{$action}%");
        }

        // Filter berdasarkan target username
        if ($target = $request->query('target')) {
            $query->where('target_username', 'LIKE', "%{$target}%");
        }

        // Filter berdasarkan router
        if ($routerId = $request->query('router_id')) {
            $query->where('router_id', $routerId);
        }

        // Filter berdasarkan tanggal
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        $logs = $query
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }
}
