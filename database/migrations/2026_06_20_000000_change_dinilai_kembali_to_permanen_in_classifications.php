<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('classifications')
            ->where('nasib_akhir', 'Dinilai Kembali')
            ->update(['nasib_akhir' => 'Permanen']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data normalisation is not reversible
    }
};
