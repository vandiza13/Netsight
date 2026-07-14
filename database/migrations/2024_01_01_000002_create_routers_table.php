<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('host', 100);
            $table->string('api_user', 50)->default('admin');
            $table->integer('api_port')->default(8729);
            $table->text('credential_encrypted'); // AES-256-CBC via Crypt::encryptString()
            $table->string('routeros_version', 10)->nullable();
            $table->integer('sync_offset_minutes'); // hash(router_id % 60)
            $table->string('status', 20)->default('HEALTHY'); // HEALTHY | DEGRADED | UNREACHABLE
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('consecutive_sync_failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
