<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PppoeUserCache extends Model
{
    use HasFactory;

    protected $table = 'pppoe_users_cache';

    public $timestamps = false;

    protected $fillable = [
        'router_id',
        'username',
        'profile',
        'package_limit_mbps',
        'is_active_last_check',
        'synced_at',
    ];

    protected $casts = [
        'is_active_last_check' => 'boolean',
        'package_limit_mbps' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
