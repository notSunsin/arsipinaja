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
        Schema::dropIfExists('storage_capacity_settings');
        Schema::dropIfExists('storage_boxes');
        Schema::dropIfExists('storage_rows');
        Schema::dropIfExists('storage_racks');

        Schema::table('archives', function (Blueprint $table) {
            $table->dropColumn(['box_number', 'file_number', 'rack_number', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            $table->unsignedInteger('box_number')->nullable()->after('skkad')->comment('Global box sequence number');
            $table->unsignedInteger('file_number')->nullable()->after('box_number')->comment('File number within box (restarts at 1 per box)');
            $table->unsignedSmallInteger('rack_number')->nullable()->after('file_number')->comment('Physical rack number');
            $table->unsignedSmallInteger('row_number')->nullable()->after('rack_number')->comment('Shelf row number');
        });

        Schema::create('storage_racks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('total_boxes')->default(0);
            $table->integer('capacity_per_box')->default(50);
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->integer('year_start')->nullable();
            $table->integer('year_end')->nullable();
            $table->timestamps();
        });

        Schema::create('storage_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('storage_racks')->onDelete('cascade');
            $table->integer('row_number');
            $table->integer('total_boxes')->default(0);
            $table->integer('available_boxes')->default(0);
            $table->enum('status', ['available', 'full', 'maintenance'])->default('available');
            $table->timestamps();

            $table->unique(['rack_id', 'row_number']);
        });

        Schema::create('storage_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('storage_racks')->onDelete('cascade');
            $table->foreignId('row_id')->constrained('storage_rows')->onDelete('cascade');
            $table->integer('box_number');
            $table->integer('archive_count')->default(0);
            $table->integer('capacity')->default(50);
            $table->enum('status', ['available', 'partially_full', 'full', 'reserved'])->default('available');
            $table->timestamps();

            $table->unique(['rack_id', 'box_number'], 'storage_boxes_rack_box_unique');
        });

        Schema::create('storage_capacity_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('storage_racks')->onDelete('cascade');
            $table->integer('default_capacity_per_box')->default(50);
            $table->integer('warning_threshold')->default(40);
            $table->boolean('auto_assign')->default(true);
            $table->timestamps();
        });
    }
};
