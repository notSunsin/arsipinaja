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
        // Change tingkat_perkembangan from enum to string
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE archives MODIFY COLUMN tingkat_perkembangan VARCHAR(255)');
        } else {
            DB::statement('ALTER TABLE archives ALTER COLUMN tingkat_perkembangan TYPE VARCHAR(255)');
        }

        // Remove the enum constraint
        if (DB::getDriverName() === 'mysql') {
            $constraint = DB::selectOne("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archives' AND CONSTRAINT_NAME = 'archives_tingkat_perkembangan_check'");
            if ($constraint) {
                DB::statement('ALTER TABLE archives DROP CHECK archives_tingkat_perkembangan_check');
            }
        } else {
            DB::statement('ALTER TABLE archives DROP CONSTRAINT IF EXISTS archives_tingkat_perkembangan_check');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to enum
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE archives MODIFY COLUMN tingkat_perkembangan ENUM("Asli", "Salinan", "Tembusan")');
        } else {
            DB::statement('ALTER TABLE archives ALTER COLUMN tingkat_perkembangan TYPE VARCHAR(255)');
        }
        DB::statement("ALTER TABLE archives ADD CONSTRAINT archives_tingkat_perkembangan_check CHECK (tingkat_perkembangan IN ('Asli', 'Salinan', 'Tembusan'))");
    }
};
