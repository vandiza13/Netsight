<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pppoe_users_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('username', 100);
            $table->string('profile', 100)->nullable();
            $table->integer('package_limit_mbps')->nullable();
            $table->boolean('is_active_last_check')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->unique(['router_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pppoe_users_cache');
    }
};
