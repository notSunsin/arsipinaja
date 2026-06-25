<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions based on actual application features
        // NOTE: print labels / storage management / locations features have
        // been removed from the app, so their permissions are dropped below.
        $permissions = [
            // Archive management permissions
            'view archives',
            'create archives',
            'edit archives',
            'delete archives',
            'export archives',
            'bulk operations',
            'evaluate archives',
            'destroy archives',

            // Master data permissions
            'manage categories',
            'manage classifications',

            // User management permissions
            'manage users',
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Role management permissions
            'manage roles',
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            // Dashboard permissions
            'view admin dashboard',
            'view intern dashboard',
            'view analytics dashboard',

            // Search permissions
            'search archives',
            'advanced search',
        ];

        // Create permissions safely (won't duplicate if they exist)
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Remove permissions left over from features that no longer exist
        // (e.g. print labels, storage management, locations, system logs/settings)
        Permission::whereNotIn('name', $permissions)->get()->each(function (Permission $permission) {
            $permission->roles()->detach();
            $permission->delete();
        });

        // 1. ADMIN ROLE - Full access to everything
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        // 2. INTERN ROLE (Mahasiswa Magang) - Basic archive operations only
        $internRole = Role::firstOrCreate(['name' => 'intern']);
        $internRole->syncPermissions([
            'view archives',
            'create archives',
            'edit archives',
            'export archives',
            'view intern dashboard',
            'search archives',
        ]);

        $this->command->info('Roles and permissions created/updated successfully!');
        $this->command->table(['Role', 'Permissions'], [
            ['admin', 'Full access to all features and system management'],
            ['intern', 'Basic archive operations + View access only'],
        ]);
    }
}
