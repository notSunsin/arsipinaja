<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $constraint = DB::selectOne("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archives' AND CONSTRAINT_NAME = 'archives_status_check'");
            if ($constraint) {
                DB::statement('ALTER TABLE archives DROP CHECK archives_status_check');
            }
        } else {
            DB::statement('ALTER TABLE archives DROP CONSTRAINT IF EXISTS archives_status_check');
        }
        DB::statement("ALTER TABLE archives ADD CONSTRAINT archives_status_check CHECK (status IN ('Aktif', 'Inaktif', 'Permanen', 'Musnah', 'Dinilai Kembali'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $constraint = DB::selectOne("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archives' AND CONSTRAINT_NAME = 'archives_status_check'");
            if ($constraint) {
                DB::statement('ALTER TABLE archives DROP CHECK archives_status_check');
            }
        } else {
            DB::statement('ALTER TABLE archives DROP CONSTRAINT IF EXISTS archives_status_check');
        }
        DB::statement("ALTER TABLE archives ADD CONSTRAINT archives_status_check CHECK (status IN ('Aktif', 'Inaktif', 'Permanen', 'Musnah'))");
    }
};
