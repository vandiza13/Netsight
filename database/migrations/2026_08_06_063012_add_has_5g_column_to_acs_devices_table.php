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
        Schema::table('acs_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('acs_devices', 'has_5g')) {
                $table->boolean('has_5g')->default(false)->after('wifi_ssid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acs_devices', function (Blueprint $table) {
            if (Schema::hasColumn('acs_devices', 'has_5g')) {
                $table->dropColumn('has_5g');
            }
        });
    }
};
