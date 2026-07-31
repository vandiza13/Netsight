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
        Schema::table('routers', function (Blueprint $table) {
            $table->integer('bandwidth_capacity_mbps')->nullable()->after('monitored_interface');
            $table->integer('warning_threshold_pct')->default(90)->after('bandwidth_capacity_mbps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn(['bandwidth_capacity_mbps', 'warning_threshold_pct']);
        });
    }
};
