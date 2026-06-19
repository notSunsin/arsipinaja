<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            if (Schema::hasColumn('archives', 're_evaluation')) {
                $table->dropColumn('re_evaluation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->boolean('re_evaluation')->default(false)->after('row_number');
        });
    }
};
