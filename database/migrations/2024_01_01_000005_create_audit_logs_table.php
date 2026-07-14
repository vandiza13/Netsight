<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_noc_id')->constrained('staff_noc')->onDelete('cascade');
            $table->string('action', 100);
            $table->string('target_username', 100)->nullable();
            $table->foreignId('router_id')->nullable()->constrained('routers')->onDelete('set null');
            $table->jsonb('metadata')->nullable(); // PostgreSQL JSONB
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
