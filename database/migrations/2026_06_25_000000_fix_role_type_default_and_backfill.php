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
        DB::statement("ALTER TABLE users MODIFY role_type ENUM('admin', 'intern') NOT NULL DEFAULT 'intern'");

        // Backfill: make role_type match each user's actual Spatie role,
        // since several code paths (registration, admin user management)
        // were assigning Spatie roles without updating this column.
        DB::table('users')->select('users.id')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', \App\Models\User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->update(['users.role_type' => 'admin']);

        DB::table('users')->select('users.id')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', \App\Models\User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'intern')
            ->update(['users.role_type' => 'intern']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role_type ENUM('admin', 'intern') NOT NULL DEFAULT 'admin'");
    }
};
