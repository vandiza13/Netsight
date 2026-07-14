<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class StaffNoc extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'staff_noc';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'totp_secret_encrypted',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        'totp_secret_encrypted',
    ];

    /**
     * Override untuk Sanctum/Auth: kolom password custom.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Decrypt TOTP secret dari kolom terenkripsi.
     * @see SECURITY.md Section 2.1
     */
    public function getTotpSecretAttribute(): ?string
    {
        if (empty($this->totp_secret_encrypted)) {
            return null;
        }

        return Crypt::decryptString($this->totp_secret_encrypted);
    }

    /**
     * Encrypt dan simpan TOTP secret.
     */
    public function setTotpSecretAttribute(?string $value): void
    {
        $this->attributes['totp_secret_encrypted'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    /**
     * Cek apakah user memiliki role minimal yang diperlukan.
     * Hierarki: ADMIN > TIER_2 > TIER_1
     */
    public function hasMinimumRole(string $minimumRole): bool
    {
        $hierarchy = [
            'TIER_1' => 1,
            'TIER_2' => 2,
            'ADMIN'  => 3,
        ];

        $userLevel = $hierarchy[$this->role] ?? 0;
        $requiredLevel = $hierarchy[$minimumRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isTier2(): bool
    {
        return in_array($this->role, ['TIER_2', 'ADMIN']);
    }

    public function isTier1(): bool
    {
        return in_array($this->role, ['TIER_1', 'TIER_2', 'ADMIN']);
    }

    /**
     * Relasi: audit logs yang dibuat oleh staf ini.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'staff_noc_id');
    }

    /**
     * Relasi: sesi Torch yang diinisiasi oleh staf ini.
     */
    public function torchSessions()
    {
        return $this->hasMany(TorchSession::class, 'initiated_by');
    }
}
