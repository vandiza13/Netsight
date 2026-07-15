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
        if (!Schema::hasColumn('public.demo_sandboxes', 'status')) {
            Schema::table('public.demo_sandboxes', function (Blueprint $table) {
                $table->string('status')->default('idle')->after('schema_name');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public.demo_sandboxes', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
