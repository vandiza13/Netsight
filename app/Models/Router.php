<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Router extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'api_user',
        'api_port',
        'credential_encrypted',
        'routeros_version',
        'sync_offset_minutes',
        'status',
        'last_synced_at',
        'consecutive_sync_failures',
    ];

    protected $hidden = [
        'credential_encrypted',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'consecutive_sync_failures' => 'integer',
        'api_port' => 'integer',
        'sync_offset_minutes' => 'integer',
    ];

    /**
     * Decrypt credential dari penyimpanan terenkripsi.
     * Credential TIDAK PERNAH tersimpan atau terekspos dalam bentuk plain text.
     * @see SECURITY.md Section 2.1
     */
    public function getCredentialAttribute(): ?string
    {
        if (empty($this->credential_encrypted)) {
            return null;
        }

        return Crypt::decryptString($this->credential_encrypted);
    }

    /**
     * Encrypt credential sebelum disimpan.
     * @see SECURITY.md Section 2.1 — AES-256-CBC via Crypt::encryptString()
     */
    public function setCredentialAttribute(string $value): void
    {
        $this->attributes['credential_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Cek apakah router sedang dalam status sehat.
     */
    public function isHealthy(): bool
    {
        return $this->status === 'HEALTHY';
    }

    /**
     * Cek apakah router sedang degraded (circuit breaker aktif).
     */
    public function isDegraded(): bool
    {
        return $this->status === 'DEGRADED';
    }

    /**
     * Tandai router sebagai degraded setelah kegagalan berturut-turut.
     * @see SRD.md Section 5
     */
    public function markDegraded(): void
    {
        $this->update([
            'status' => 'DEGRADED',
            'consecutive_sync_failures' => config('netsight.sync.circuit_breaker_threshold'),
        ]);
    }

    /**
     * Reset status router ke healthy.
     */
    public function markHealthy(): void
    {
        $this->update([
            'status' => 'HEALTHY',
            'consecutive_sync_failures' => 0,
        ]);
    }

    public function pppoeUsersCache()
    {
        return $this->hasMany(PppoeUserCache::class);
    }

    public function torchSessions()
    {
        return $this->hasMany(TorchSession::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
