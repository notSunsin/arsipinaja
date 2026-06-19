<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Archive;
use App\Models\User;
use App\Jobs\UpdateArchiveStatusJob;
use Illuminate\Support\Facades\Log;

class FakeDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('FakeDataSeeder: Starting fake data generation...');

        // Step 1: Create Categories
        Log::info('FakeDataSeeder: Creating categories...');
        $categories = Category::factory(10)->create();
        Log::info("FakeDataSeeder: Created {$categories->count()} categories");

        // Step 2: Create Classifications
        Log::info('FakeDataSeeder: Creating classifications...');
        $classifications = Classification::factory(30)->create();
        Log::info("FakeDataSeeder: Created {$classifications->count()} classifications");

        // Step 3: Ensure we have 2 users (admin, intern)
        Log::info('FakeDataSeeder: Ensuring 2 users exist...');
        $this->ensureUsersExist();

        // Step 4: Create Archives with distribution across users
        Log::info('FakeDataSeeder: Creating archives...');
        $this->createArchivesWithUserDistribution();

        // Step 5: Update archive statuses based on dates
        Log::info('FakeDataSeeder: Updating archive statuses...');
        $this->updateArchiveStatuses();

        Log::info('FakeDataSeeder: Fake data generation completed!');
    }

    /**
     * Ensure 3 users exist with proper roles
     */
    private function ensureUsersExist(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@archivy.test'],
            [
                'name' => 'Administrator',
                'email' => 'admin@archivy.test',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Create intern user
        $intern = User::firstOrCreate(
            ['email' => 'intern@archivy.test'],
            [
                'name' => 'Mahasiswa Magang',
                'email' => 'intern@archivy.test',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );
        if (!$intern->hasRole('intern')) {
            $intern->assignRole('intern');
        }

        Log::info("FakeDataSeeder: Users ready - Admin: {$admin->id}, Intern: {$intern->id}");
    }

    /**
     * Create archives with distribution across users
     */
    private function createArchivesWithUserDistribution(): void
    {
        $admin = User::role('admin')->first();
        $intern = User::role('intern')->first();

        // Create archives with distribution across users
        $totalArchives = 80;

        // 50% by admin (40 archives)
        $adminArchives = Archive::factory(40)->create([
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        // 50% by intern (40 archives)
        $internArchives = Archive::factory(40)->create([
            'created_by' => $intern->id,
            'updated_by' => $intern->id,
        ]);

        Log::info("FakeDataSeeder: Created {$totalArchives} archives total");
        Log::info("FakeDataSeeder: - Admin created: {$adminArchives->count()}");
        Log::info("FakeDataSeeder: - Intern created: {$internArchives->count()}");
    }

    /**
     * Update archive statuses based on dates
     */
    private function updateArchiveStatuses(): void
    {
        // Run the status update job to calculate proper statuses
        UpdateArchiveStatusJob::dispatchSync();

        // Get status distribution
        $aktifCount = Archive::where('status', 'Aktif')->count();
        $inaktifCount = Archive::where('status', 'Inaktif')->count();
        $musnahCount = Archive::where('status', 'Musnah')->count();

        Log::info("FakeDataSeeder: Archive status distribution:");
        Log::info("FakeDataSeeder: - Aktif: {$aktifCount}");
        Log::info("FakeDataSeeder: - Inaktif: {$inaktifCount}");
        Log::info("FakeDataSeeder: - Musnah: {$musnahCount}");
    }
}
