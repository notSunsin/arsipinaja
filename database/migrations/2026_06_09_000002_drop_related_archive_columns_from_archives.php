<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            if (Schema::hasColumn('archives', 'parent_archive_id')) {
                $table->dropForeign(['parent_archive_id']);
                $table->dropColumn('parent_archive_id');
            }
            if (Schema::hasColumn('archives', 'is_parent')) {
                $table->dropColumn('is_parent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_archive_id')->nullable()->after('updated_by');
            $table->boolean('is_parent')->default(false)->after('parent_archive_id');
            $table->foreign('parent_archive_id')->references('id')->on('archives')->onDelete('set null');
        });
    }
};
