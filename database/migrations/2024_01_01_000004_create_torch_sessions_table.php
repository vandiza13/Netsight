<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torch_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('username', 100);
            $table->string('session_id_snapshot', 100)->nullable();
            $table->string('dynamic_interface', 150)->nullable();
            $table->foreignId('initiated_by')->constrained('staff_noc')->onDelete('cascade');
            $table->string('tag', 50);
            $table->string('status', 20)->default('RUNNING'); // RUNNING | COMPLETED | CANCELLED | FORCE_TERMINATED
            $table->boolean('auto_cleanup')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torch_sessions');
    }
};
