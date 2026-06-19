# Backup: Storage Feature Removal

Date: 2026-06-19
App version before this change: Arsipinaja v0.1
App version after this change: Arsipinaja v0.2 (see `config/version.php`)

## What changed

The "Storage Location" / "Storage Management" feature (rack/row/box management,
"Lokasi Penyimpanan" and "Konfigurasi Storage" menus) was removed from the admin
and intern panels, including:

- Controllers: `StorageLocationController`, `StorageManagementController`,
  `Api\StorageController`, `GenerateLabelController` (orphaned dependent)
- Models: `StorageRack`, `StorageBox`, `StorageRow`, `StorageCapacitySetting`
- Service: `StorageUpdateService`
- Observer: `ArchiveObserver` (auto box-count updates) + its registration in
  `AppServiceProvider`
- Console command: `FixStorageBoxCountsCommand`
- Seeder: `StorageManagementSeeder`
- Views: `admin/storage*`, `admin/storage-management*`, `intern/storage*`,
  `intern/storage-management*`, `admin/archives/edit-location.blade.php`,
  `intern/archives/edit-location.blade.php`, top-level `resources/views/storage/*`
- Routes in `routes/web.php` (admin + intern groups) and `routes/api.php`
- `Archive` model: `rack_number`, `box_number`, `row_number`, `file_number`
  fillable fields, `storageRack()`/`storageBox()` relations, location scopes
  (`withLocation`/`withoutLocation`), `hasStorageLocation()`,
  `getStorageLocationAttribute()`, `getNextFileNumber*()` static helpers
- `archives` table columns: `rack_number`, `box_number`, `row_number`,
  `file_number` (dropped via new migration
  `2026_06_19_000000_remove_storage_feature.php`, NOT by editing/deleting the
  original migrations)
- `BulkOperationController::bulkMoveStorage()` + `bulk/move-storage` routes
- `ArchiveController`: `editLocation`, `updateLocation`, `getRackRows`,
  `getRackRowBoxes`, and dead code (`bulkUpdateLocation` and its definitive-number
  helpers) that depended on the storage tables
- Sidebar "Manajemen Storage" menu in `resources/views/layouts/navigation.blade.php`
- `User::getStorageRoute()` (unused), `ArchiveAutomationService::autoAssignStorage()`
  and related dead helpers (unreachable code path)

Excel export classes (`ArchiveAktifExport`, `ArchiveInaktifPermanenExport`,
`ArchiveStatusExport`) and `intern/archives/export-preview.blade.php` still
reference `rack_number`/`box_number`/`row_number`/`storageRack` in a few cells —
these were left as-is (they degrade gracefully to `'-'`, no errors) to avoid
blindly restructuring Excel column layouts (merged headers/widths) without being
able to visually verify the output.

## How to restore (downgrade to v0.1)

1. Roll back the column/table drop:
   ```
   php artisan migrate:rollback --step=1
   ```
   (only if `2026_06_19_000000_remove_storage_feature.php` has already run;
   this recreates `storage_racks`/`storage_rows`/`storage_boxes`/
   `storage_capacity_settings` and the 4 archive columns, but does NOT restore
   data that existed in those tables/columns before removal — restore from a
   database backup separately if you need the old data).

2. Copy every file from this backup folder back to its original path,
   overwriting the current version. The relative path inside this folder
   matches the original path in the project, e.g.:
   ```
   backup/2026-06-18_storage-feature-removal/app/Http/Controllers/StorageLocationController.php
   -> app/Http/Controllers/StorageLocationController.php
   ```

3. Delete the new migration file added during this change:
   ```
   database/migrations/2026_06_19_000000_remove_storage_feature.php
   ```

4. Delete `config/version.php` if you don't want the version marker, or just
   change `version` back to `0.1`.

5. Run `composer dump-autoload` and clear caches:
   ```
   php artisan optimize:clear
   ```
