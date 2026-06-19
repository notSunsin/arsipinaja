<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update archives: status Permanen/Dinilai Kembali/Berkas Perseorangan → Musnah
        DB::table('archives')
            ->whereIn('status', ['Permanen', 'Dinilai Kembali', 'Berkas Perseorangan'])
            ->update(['status' => 'Musnah']);

        // Update archives: manual_nasib_akhir Permanen/Dinilai Kembali/Masuk ke Berkas Perseorangan → Musnah
        DB::table('archives')
            ->whereIn('manual_nasib_akhir', ['Permanen', 'Dinilai Kembali', 'Masuk ke Berkas Perseorangan'])
            ->update(['manual_nasib_akhir' => 'Musnah']);

        // Update classifications: nasib_akhir Permanen/Dinilai Kembali → Musnah
        DB::table('classifications')
            ->whereIn('nasib_akhir', ['Permanen', 'Dinilai Kembali', 'Masuk ke Berkas Perseorangan'])
            ->update(['nasib_akhir' => 'Musnah']);

        // Update categories: nasib_akhir Permanen/Dinilai Kembali → Musnah (if the column exists)
        if (Schema::hasColumn('categories', 'nasib_akhir')) {
            DB::table('categories')
                ->whereIn('nasib_akhir', ['Permanen', 'Dinilai Kembali', 'Masuk ke Berkas Perseorangan'])
                ->update(['nasib_akhir' => 'Musnah']);
        }
    }

    public function down(): void
    {
        // Data normalisation is not reversible
    }
};
