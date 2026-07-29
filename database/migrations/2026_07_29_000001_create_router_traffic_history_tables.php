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
        Schema::create('router_traffic_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('interface');
            $table->unsignedBigInteger('rx')->default(0);
            $table->unsignedBigInteger('tx')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['router_id', 'created_at']);
        });

        Schema::create('router_traffic_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('interface');
            $table->unsignedBigInteger('rx_avg')->default(0);
            $table->unsignedBigInteger('rx_max')->default(0);
            $table->unsignedBigInteger('tx_avg')->default(0);
            $table->unsignedBigInteger('tx_max')->default(0);
            $table->timestamp('period_start');

            $table->index(['router_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_traffic_trends');
        Schema::dropIfExists('router_traffic_histories');
    }
};
