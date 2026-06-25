<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $maxId = DB::selectOne('SELECT MAX(id) AS max_id FROM categories');
            $next = ($maxId->max_id ?? 0) + 1;
            DB::statement("ALTER TABLE categories AUTO_INCREMENT = {$next}");
        } else {
            // Fix PostgreSQL sequence for categories table
            DB::statement('SELECT setval(pg_get_serial_sequence(\'categories\', \'id\'), (SELECT MAX(id) FROM categories))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this
    }
};
