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
        if (!Schema::hasTable('acs_devices')) {
            Schema::create('acs_devices', function (Blueprint $table) {
                $table->id();
                // GenieACS Device ID (usually MAC or OUI-SerialNumber)
                $table->string('genieacs_id')->unique();
                
                // Basic Device Info
                $table->string('serial_number')->nullable()->index();
                $table->string('mac_address')->nullable()->index();
                $table->string('vendor')->nullable();
                $table->string('model')->nullable();
                $table->string('hardware_version')->nullable();
                $table->string('software_version')->nullable();
                
                // Network & Diagnostics
                $table->string('ip_address')->nullable();
                $table->decimal('rx_power_dbm', 8, 2)->nullable();
                $table->string('wifi_ssid')->nullable();
                
                // PPPoE Mapping
                $table->string('pppoe_username')->nullable()->index();
                
                // Connection Status
                $table->enum('status', ['online', 'offline', 'unprovisioned'])->default('offline');
                $table->timestamp('last_inform_at')->nullable();
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_devices');
    }
};
