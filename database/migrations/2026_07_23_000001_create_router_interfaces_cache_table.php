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
        Schema::create('router_interfaces_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->string('name'); // e.g. ether1, sfp1, vlan10
            $table->string('type')->default('ethernet'); // ethernet, sfp, wlan, vlan, etc.
            $table->string('mac_address')->nullable();
            $table->boolean('is_running')->default(false);
            $table->boolean('is_disabled')->default(false);
            $table->string('link_speed')->nullable(); // e.g. 1Gbps, 100Mbps
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->unique(['router_id', 'name']);
            $table->index(['router_id', 'is_running']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_interfaces_cache');
    }
};
