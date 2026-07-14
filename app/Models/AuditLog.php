<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit Log — append-only.
 * Log audit TIDAK BOLEH dihapus atau diedit oleh role manapun.
 * @see SECURITY.md Section 6
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'staff_noc_id',
        'action',
        'target_username',
        'router_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot: auto-set created_at pada creation.
     */
    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $log->created_at = $log->created_at ?? now();
        });
    }

    public function staff()
    {
        return $this->belongsTo(StaffNoc::class, 'staff_noc_id');
    }

    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * Helper: buat audit log entry.
     * Format: [Timestamp] - [User] - [Aksi] - [Target] - [Router]
     * @see SECURITY.md Section 6
     */
    public static function record(
        int $staffId,
        string $action,
        ?string $targetUsername = null,
        ?int $routerId = null,
        array $metadata = []
    ): self {
        return self::create([
            'staff_noc_id' => $staffId,
            'action' => $action,
            'target_username' => $targetUsername,
            'router_id' => $routerId,
            'metadata' => $metadata,
        ]);
    }
}
