<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'public.demo_sandboxes' : 'demo_sandboxes';

        try {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('schema_name', 50)->unique();
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Error code 42P07 means "relation already exists" in PostgreSQL
            if ($e->getCode() !== '42P07') {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'public.demo_sandboxes' : 'demo_sandboxes';
        Schema::dropIfExists($tableName);
    }
};
