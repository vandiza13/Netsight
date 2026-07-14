<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TorchSession extends Model
{
    use HasFactory;

    protected $table = 'torch_sessions';

    public $timestamps = false;

    protected $fillable = [
        'router_id',
        'username',
        'session_id_snapshot',
        'dynamic_interface',
        'initiated_by',
        'tag',
        'status',
        'auto_cleanup',
        'started_at',
        'ended_at',
        'traffic_samples',
        'app_distribution',
        'diagnostic_conclusion',
        'peak_tx_bps',
        'peak_rx_bps',
        'avg_tx_bps',
        'avg_rx_bps',
    ];

    protected $casts = [
        'auto_cleanup' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'traffic_samples' => 'array',
        'app_distribution' => 'array',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    public function initiator()
    {
        return $this->belongsTo(StaffNoc::class, 'initiated_by');
    }

    /**
     * Scope: hanya sesi yang sedang berjalan.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'RUNNING');
    }

    /**
     * Scope: sesi yang di-force-terminate oleh watchdog.
     * @see SECURITY.md Section 6 — auto_cleanup harus terpisah dari pembatalan manual
     */
    public function scopeAutoCleanedUp($query)
    {
        return $query->where('auto_cleanup', true);
    }
}
