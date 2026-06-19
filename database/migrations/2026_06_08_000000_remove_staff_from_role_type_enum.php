<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing staff users to intern before changing enum
        DB::table('users')->where('role_type', 'staff')->update(['role_type' => 'intern']);

        // Remove 'staff' option from enum (MySQL requires full column redefinition)
        DB::statement("ALTER TABLE users MODIFY COLUMN role_type ENUM('admin', 'intern') NOT NULL DEFAULT 'admin'");

        // Remove staff role and its permissions from Spatie tables
        DB::table('model_has_roles')
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('name', 'staff');
            })
            ->delete();

        DB::table('role_has_permissions')
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('name', 'staff');
            })
            ->delete();

        DB::table('roles')->where('name', 'staff')->delete();

        // Remove 'view staff dashboard' permission if it exists
        $permission = DB::table('permissions')->where('name', 'view staff dashboard')->first();
        if ($permission) {
            DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role_type ENUM('admin', 'staff', 'intern') NOT NULL DEFAULT 'admin'");
    }
};
