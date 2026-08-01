<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('olt_onu_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_onu_id')->constrained('olt_onus')->cascadeOnDelete();
            $table->decimal('rx_power_dbm', 6, 2)->nullable();
            $table->decimal('tx_power_dbm', 6, 2)->nullable();
            $table->string('status', 50)->default('offline');
            $table->timestamp('created_at')->useCurrent();
            
            // Index for fast querying by ONU and cleanup
            $table->index(['olt_onu_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_onu_histories');
    }
};
