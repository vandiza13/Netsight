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
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->integer('snmp_port')->default(161);
            $table->string('snmp_community')->default('public');
            $table->string('technology')->default('epon'); // epon / gpon
            $table->string('vendor_code')->default('hioso'); // hioso, vsol_epon, vsol_gpon, hsan, zte_c320, huawei_ma, bdcom, custom
            $table->string('status')->default('offline'); // online / offline
            $table->integer('total_pons')->default(2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_onus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->onDelete('cascade');
            $table->string('pon_port')->default('1');
            $table->integer('onu_index')->default(1);
            $table->string('serial_number')->nullable()->index();
            $table->string('mac_address')->nullable()->index();
            $table->string('onu_description')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('pppoe_username')->nullable()->index();
            $table->string('status')->default('offline'); // online, offline, los
            $table->decimal('rx_power_dbm', 5, 2)->nullable();
            $table->decimal('tx_power_dbm', 5, 2)->nullable();
            $table->integer('distance_meters')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['olt_id', 'pon_port', 'onu_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_onus');
        Schema::dropIfExists('olts');
    }
};
