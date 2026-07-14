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
        Schema::table('torch_sessions', function (Blueprint $table) {
            $table->jsonb('traffic_samples')->nullable();
            $table->jsonb('app_distribution')->nullable();
            $table->text('diagnostic_conclusion')->nullable();
            $table->bigInteger('peak_tx_bps')->nullable();
            $table->bigInteger('peak_rx_bps')->nullable();
            $table->bigInteger('avg_tx_bps')->nullable();
            $table->bigInteger('avg_rx_bps')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('torch_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'traffic_samples',
                'app_distribution',
                'diagnostic_conclusion',
                'peak_tx_bps',
                'peak_rx_bps',
                'avg_tx_bps',
                'avg_rx_bps',
            ]);
        });
    }
};
