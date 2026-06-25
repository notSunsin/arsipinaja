<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\Category;
use App\Models\Classification;
use App\Http\Requests\StoreArchiveRequest;
use App\Http\Requests\UpdateArchiveRequest;
use App\Jobs\UpdateArchiveStatusJob;
use App\Exports\ArchiveExportWithHeader;
use App\Exports\ArchiveAktifExport;
use App\Exports\ArchiveMusnahExport;
use App\Exports\ArchiveInaktifPermanenExport;
use App\Exports\ArchiveStatusExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Models\User;
use App\Services\ArchiveAutomationService;


class ArchiveController extends Controller
{
    /**
     * Display all archives (main archive page with add button)
     */
    public function index(Request $request)
    {
        // Get all archives (not just latest)
        $query = Archive::with(['category', 'classification', 'createdByUser', 'updatedByUser'])
            ->orderBy('kurun_waktu_start', 'desc');

        // Apply filters
        $query = $this->applyFilters($query, $request);

        $archives = $query->latest()->paginate($request->get('per_page', 25));

        $title = 'Semua Arsip';
        $showAddButton = $this->canCreateArchive();
        $showActionButtons = true; // Show action buttons for all archives

        // Get filter data
        $categories = Category::orderBy('nama_kategori')->get();
        $classifications = Classification::with('category')->orderBy('nama_klasifikasi')->get();
        $users = $this->getFilterUsers();

        $viewPath = $this->getViewPath('archives.index');
        return view($viewPath, compact('archives', 'title', 'showAddButton', 'showActionButtons', 'categories', 'classifications', 'users'));
    }

    /**
     * Display active archives only
     */
    public function aktif(Request $request)
    {
        $query = Archive::aktif()->with(['category', 'classification', 'createdByUser', 'updatedByUser']);

        // Apply filters
        $query = $this->applyFilters($query, $request);

        $archives = $query->latest()->paginate($request->get('per_page', 25));

        $title = 'Arsip Aktif';
        $showAddButton = false;
        $showActionButtons = true; // Show Edit, Show, and Delete buttons

        // Get filter data
        $categories = Category::orderBy('nama_kategori')->get();
        $classifications = Classification::with('category')->orderBy('nama_klasifikasi')->get();
        $users = $this->getFilterUsers();

        $viewPath = $this->getViewPath('archives.index');
        return view($viewPath, compact('archives', 'title', 'showAddButton', 'showActionButtons', 'categories', 'classifications', 'users'));
    }

    /**
     * Display inactive archives only
     */
    public function inaktif(Request $request)
    {
        $query = Archive::inaktif()->with(['category', 'classification', 'createdByUser', 'updatedByUser']);

        // Apply filters
        $query = $this->applyFilters($query, $request);

        $archives = $query->latest()->paginate($request->get('per_page', 25));

        $title = 'Arsip Inaktif';
        $showAddButton = false;
        $showActionButtons = true; // Show Edit, Show, and Delete buttons

        // Get filter data
        $categories = Category::orderBy('nama_kategori')->get();
        $classifications = Classification::with('category')->orderBy('nama_klasifikasi')->get();
        $users = $this->getFilterUsers();

        $viewPath = $this->getViewPath('archives.index');
        return view($viewPath, compact('archives', 'title', 'showAddButton', 'showActionButtons', 'categories', 'classifications', 'users'));
    }

    /**
     * Display permanent archives only
     */
    public function permanen(Request $request)
    {
        $query = Archive::permanen()->with(['category', 'classification', 'createdByUser', 'updatedByUser']);

        $query = $this->applyFilters($query, $request);

        $archives = $query->latest()->paginate($request->get('per_page', 25));

        $title = 'Arsip Permanen';
        $showAddButton = false;
        $showActionButtons = true;

        $categories = Category::orderBy('nama_kategori')->get();
        $classifications = Classification::with('category')->orderBy('nama_klasifikasi')->get();
        $users = $this->getFilterUsers();

        $viewPath = $this->getViewPath('archives.index');
        return view($viewPath, compact('archives', 'title', 'showAddButton', 'showActionButtons', 'categories', 'classifications', 'users'));
    }

    /**
     * Display destroyed archives only
     */
    public function musnah(Request $request)
    {
        $query = Archive::musnah()->with(['category', 'classification', 'createdByUser', 'updatedByUser']);

        // Apply filters
        $query = $this->applyFilters($query, $request);

        $archives = $query->latest()->paginate($request->get('per_page', 25));

        $title = 'Arsip Musnah';
        $showAddButton = false;
        $showStatusActions = true; // Allow status changes from musnah page
        $showActionButtons = true; // Show Edit, Show, and Delete buttons

        // Get filter data
        $categories = Category::orderBy('nama_kategori')->get();
        $classifications = Classification::with('category')->orderBy('nama_klasifikasi')->get();
        $users = $this->getFilterUsers();

        $viewPath = $this->getViewPath('archives.index');
        return view($viewPath, compact('archives', 'title', 'showAddButton', 'showStatusActions', 'showActionButtons', 'categories', 'classifications', 'users'));
    }

    /**
     * Change archive status via AJAX
     */
    public function changeStatus(Request $request)
    {
        $request->validate([
            'archive_id' => 'required|exists:archives,id',
            'status' => 'required|in:Aktif,Inaktif,Permanen,Musnah'
        ]);

        try {
            $archive = Archive::findOrFail($request->archive_id);
            $oldStatus = $archive->status;

            $archive->update([
                'status' => $request->status,
                'manual_status_override' => true,
                'manual_override_at' => now(),
                'manual_override_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            Log::info("Status change: Archive ID {$archive->id} changed from {$oldStatus} to {$request->status} by user " . Auth::id());

            return response()->json([
                'success' => true,
                'message' => "Status arsip berhasil diubah menjadi {$request->status}",
                'archive_id' => $archive->id,
                'new_status' => $request->status
            ]);
        } catch (\Exception $e) {
            Log::error('Status change error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get classification details for AJAX
     */
    public function getClassificationDetails(Classification $classification)
    {
        $classification->load('category');
        return response()->json($classification);
    }

    /**
     * Get classifications by category for AJAX
     */
    public function getClassificationsByCategory(Request $request)
    {
        $classifications = Classification::query()
            ->where('category_id', $request->query('category_id'))
            ->with('category')
            ->get();
        return response()->json($classifications);
    }

    /**
     * Generate automatic index number with better readability
     */
    // private function generateIndexNumber(Classification $classification, $kurunWaktuStart)
    // {
    //     $year = Carbon::parse($kurunWaktuStart)->year;

    //     // Get current year's archive count for sequential numbering
    //     $currentYearCount = Archive::whereYear('kurun_waktu_start', $year)->count();
    //     $nextSequence = $currentYearCount + 1;

    //     // Format: ARK/YYYY/KODE-KLASIFIKASI/NNNN
    //     // Example: ARK/2024/01.02/0001
    //     return sprintf('ARK/%d/%s/%04d', $year, $classification->code, $nextSequence);
    // }

    /**
     * Calculate and update archive status immediately
     */
    private function calculateAndSetStatus(Archive $archive)
    {
        $today = today();
        $status = 'Aktif'; // Default

        if ($archive->transition_inactive_due <= $today) {
            $status = $this->resolveFinalStatus($archive);
        } elseif ($archive->transition_active_due <= $today) {
            $status = 'Inaktif';
        }

        $archive->update(['status' => $status]);
        return $status;
    }

    /**
     * Resolve the final disposition status (Musnah/Permanen) once retention has fully elapsed,
     * based on the classification's nasib_akhir (or manual override for manual-input archives).
     */
    private function resolveFinalStatus(Archive $archive): string
    {
        if ($archive->is_manual_input && $archive->manual_nasib_akhir) {
            return $archive->manual_nasib_akhir;
        }

        return $archive->classification->nasib_akhir ?? 'Musnah';
    }

    /**
     * Check if archive requires manual input based on category and retention values
     */
    private function requiresManualInput(Archive $archive): bool
    {
        // LAINNYA category always requires manual input
        if ($archive->category && $archive->category->nama_kategori === 'LAINNYA') {
            return true;
        }

        // Check if classification has any retention field = 0 (indicating manual input needed)
        if ($archive->classification) {
            $classification = $archive->classification;
            if ($classification->retention_aktif === 0 || $classification->retention_inaktif === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the tembusan list from the request, normalized to null when "tidak_ada".
     */
    private function resolveTembusan(Request $request): ?array
    {
        if ($request->input('tembusan_status') !== 'ada') {
            return null;
        }

        $tembusan = array_values(array_filter(
            (array) $request->input('tembusan', []),
            fn ($t) => trim((string) $t) !== ''
        ));

        return empty($tembusan) ? null : $tembusan;
    }

    /**
     * Store the uploaded archive file (if any) and return the data to merge into the archive.
     * Deletes the previous file from storage when replacing it on an existing archive.
     */
    private function handleArchiveFileUpload(Request $request, ?Archive $existingArchive = null): array
    {
        if (!$request->hasFile('file')) {
            return [];
        }

        if ($existingArchive && $existingArchive->file_path) {
            Storage::disk('public')->delete($existingArchive->file_path);
        }

        $file = $request->file('file');
        $path = $file->store('archives', 'public');

        return [
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime_type' => $file->getClientMimeType(),
        ];
    }

    /**
     * Get retention values for archive (handles hybrid cases)
     */
    private function getRetentionValues(Archive $archive, $validated = null): array
    {
        $classification = $archive->classification;

        // Case 1: LAINNYA category - always manual
        if ($archive->category && $archive->category->nama_kategori === 'LAINNYA') {
            return [
                'retention_aktif' => (int)($validated['manual_retention_aktif'] ?? 0),
                'retention_inaktif' => (int)($validated['manual_retention_inaktif'] ?? 0),
                'nasib_akhir' => $validated['manual_nasib_akhir'] ?? 'Musnah'
            ];
        }

        // Case 2: Hybrid cases - some fields manual, some from database
        $retentionAktif = $classification->retention_aktif;
        $retentionInaktif = $classification->retention_inaktif;
        $nasibAkhir = $classification->nasib_akhir;

        // If retention_aktif = 0, use manual input
        if ($retentionAktif === 0 && isset($validated['manual_retention_aktif'])) {
            $retentionAktif = (int)$validated['manual_retention_aktif'];
        }

        // If retention_inaktif = 0, use manual input
        if ($retentionInaktif === 0 && isset($validated['manual_retention_inaktif'])) {
            $retentionInaktif = (int)$validated['manual_retention_inaktif'];
        }

        return [
            'retention_aktif' => $retentionAktif,
            'retention_inaktif' => $retentionInaktif,
            'nasib_akhir' => $nasibAkhir
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!$this->canCreateArchive()) {
            abort(403, 'Access denied. You do not have permission to create archives.');
        }

        $categories = Category::all();
        $classifications = Classification::with('category')->get();
        $viewPath = $this->getViewPath('archives.create');
        return view($viewPath, compact('categories', 'classifications'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArchiveRequest $request)
    {
        $validated = $request->validated();

        try {
            $classification = Classification::with('category')->findOrFail($validated['classification_id']);
            $category = $classification->category;

            // Use manual index_number directly (no auto-generation)
            $indexNumber = $validated['index_number'];

            // Create temporary archive object for retention calculation
            $tempArchive = new Archive();
            $tempArchive->classification = $classification;
            $tempArchive->category = $category;

            // Get retention values (handles hybrid cases)
            $retentionValues = $this->getRetentionValues($tempArchive, $validated);
            $retentionAktif = $retentionValues['retention_aktif'];
            $retentionInaktif = $retentionValues['retention_inaktif'];

            // Calculate transition dates
            $kurunWaktuStart = Carbon::parse($validated['kurun_waktu_start']);
            $transitionActiveDue = $kurunWaktuStart->copy()->addYears($retentionAktif);
            $transitionInactiveDue = $transitionActiveDue->copy()->addYears($retentionInaktif);

            // Prepare archive data
            $archiveData = array_merge($validated, [
                'category_id' => $category->id,
                'index_number' => $indexNumber,
                'retention_aktif' => $retentionAktif,
                'retention_inaktif' => $retentionInaktif,
                'transition_active_due' => $transitionActiveDue,
                'transition_inactive_due' => $transitionInactiveDue,
                'status' => 'Aktif', // Initial status
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'tembusan' => $this->resolveTembusan($request),
            ], $this->handleArchiveFileUpload($request));

            unset($archiveData['file'], $archiveData['tembusan_status']);

            // Add manual fields if needed
            if ($this->requiresManualInput($tempArchive)) {
                $archiveData['is_manual_input'] = true;
                $archiveData['manual_retention_aktif'] = $validated['manual_retention_aktif'] ?? null;
                $archiveData['manual_retention_inaktif'] = $validated['manual_retention_inaktif'] ?? null;
                $archiveData['manual_nasib_akhir'] = $validated['manual_nasib_akhir'] ?? null;

            }

            // Create the archive
            $archive = Archive::create($archiveData);

            // Load classification relationship for status calculation
            $archive->load('classification');

            $finalStatus = $this->calculateAndSetStatus($archive);

            // Auto-process archive (year detection and sorting)
            $automationService = new ArchiveAutomationService();
            $automationService->autoProcessArchive($archive);

            $user = Auth::user();
            // Determine redirect route based on user role using Spatie Permission
            if ($user->roles->contains('name', 'admin')) {
                $redirectRoute = 'admin.archives.index';
            } elseif ($user->roles->contains('name', 'staff')) {
                $redirectRoute = 'staff.archives.index';
            } elseif ($user->roles->contains('name', 'intern')) {
                $redirectRoute = 'intern.archives.index';
            } else {
                $redirectRoute = 'staff.archives.index'; // Default fallback
            }

            return redirect()->route($redirectRoute)->with([
                'create_success' => "Berhasil menyimpan arsip dengan status {$finalStatus}!",
                'new_archive_id' => $archive->id,
                'show_location_options' => true
            ]);
        } catch (Throwable $e) {
            Log::error('Archive creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return redirect()->back()->withInput()->with('error', '❌ Gagal membuat arsip: ' . $e->getMessage() . '. Silakan periksa data dan coba lagi.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Archive $archive)
    {
        $archive->load(['category', 'classification.category', 'createdByUser', 'updatedByUser']);
        $viewPath = $this->getViewPath('archives.show');
        return view($viewPath, compact('archive'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Archive $archive)
    {
        $user = Auth::user();

        // Permission check: Admin and Staff can edit any archive, Intern can only edit their own
        if ($user->roles->contains('name', 'admin') || $user->roles->contains('name', 'staff') || $user->roles->contains('name', 'intern')) {
            // If user is intern, they can only edit archives they created
            if ($user->roles->contains('name', 'intern') && $archive->created_by !== $user->id) {
                abort(403, 'Access denied. You can only edit archives that you created.');
            }
        } else {
            abort(403, 'Access denied. You do not have permission to edit archives.');
        }

        $categories = Category::all();
        $classifications = Classification::with('category')->get();
        $viewPath = $this->getViewPath('archives.edit');
        return view($viewPath, compact('archive', 'categories', 'classifications'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArchiveRequest $request, Archive $archive)
    {
        $user = Auth::user();

        // Permission check: Admin and Staff can edit any archive, Intern can only edit their own
        if ($user->roles->contains('name', 'admin') || $user->roles->contains('name', 'staff') || $user->roles->contains('name', 'intern')) {
            // If user is intern, they can only edit archives they created
            if ($user->roles->contains('name', 'intern') && $archive->created_by !== $user->id) {
                abort(403, 'Access denied. You can only edit archives that you created.');
            }
        } else {
            abort(403, 'Access denied. You do not have permission to edit archives.');
        }

        $validated = $request->validated();

        try {
            $classification = Classification::with('category')->findOrFail($validated['classification_id']);
            $category = $classification->category;

            // Handle index number based on input type
            $indexNumber = $validated['index_number'];

            // Handle retention values
            $isManualInput = $validated['is_manual_input'] ?? false;

            if ($isManualInput) {
                // Use manual retention values
                $retentionAktif = (int)($validated['manual_retention_aktif'] ?? 0);
                $retentionInaktif = (int)($validated['manual_retention_inaktif'] ?? 0);
            } else {
                // Use classification retention values
                $retentionAktif = (int)$classification->retention_aktif;
                $retentionInaktif = (int)$classification->retention_inaktif;
            }

            // Calculate transition dates
            $kurunWaktuStart = Carbon::parse($validated['kurun_waktu_start']);
            $transitionActiveDue = $kurunWaktuStart->copy()->addYears($retentionAktif);
            $transitionInactiveDue = $transitionActiveDue->copy()->addYears($retentionInaktif);

            // Prepare archive data
            $archiveData = array_merge($validated, [
                'category_id' => $category->id,
                'index_number' => $indexNumber,
                'retention_aktif' => $retentionAktif,
                'retention_inaktif' => $retentionInaktif,
                'transition_active_due' => $transitionActiveDue,
                'transition_inactive_due' => $transitionInactiveDue,
                'updated_by' => Auth::id(),
                'tembusan' => $this->resolveTembusan($request),
            ], $this->handleArchiveFileUpload($request, $archive));

            unset($archiveData['file'], $archiveData['tembusan_status']);

            // Update the archive
            $archive->update($archiveData);

            // Load classification relationship for status calculation
            $archive->load('classification');
            $finalStatus = $this->calculateAndSetStatus($archive);

            // Auto-process archive (year detection and sorting)
            $automationService = new ArchiveAutomationService();
            $automationService->autoProcessArchive($archive);


            $user = Auth::user();
            // Determine redirect route based on user role using Spatie Permission
            if ($user->roles->contains('name', 'admin')) {
                $redirectRoute = 'admin.archives.index';
            } elseif ($user->roles->contains('name', 'staff')) {
                $redirectRoute = 'staff.archives.index';
            } elseif ($user->roles->contains('name', 'intern')) {
                $redirectRoute = 'intern.archives.index';
            } else {
                $redirectRoute = 'staff.archives.index'; // Default fallback
            }

            return redirect()->route($redirectRoute)->with('success', "Berhasil memperbarui arsip dengan status {$finalStatus}!");
        } catch (Throwable $e) {
            Log::error('Archive update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return redirect()->back()->withInput()->with('error', '❌ Gagal memperbarui arsip: ' . $e->getMessage() . '. Silakan periksa data dan coba lagi.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Archive $archive)
    {
        $user = Auth::user();

        // Permission check: Intern can only delete archives they created; admin and staff can delete any
        if ($user->role_type === 'intern' && (int) $archive->created_by !== (int) $user->id) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda hanya dapat menghapus arsip yang Anda buat sendiri.'], 403);
            }
            abort(403, 'Akses ditolak. Anda hanya dapat menghapus arsip yang Anda buat sendiri.');
        }

        try {
            $archiveDescription = $archive->description;
            $archiveNumber = $archive->index_number;

            // Log the deletion for audit trail
            Log::info("Archive deleted: ID {$archive->id}, Description: {$archiveDescription}, Number: {$archiveNumber}, Deleted by user: " . Auth::id());

            if ($archive->file_path) {
                Storage::disk('public')->delete($archive->file_path);
            }

            $archive->delete();

            // Handle AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "✅ Berhasil menghapus arsip ({$archiveNumber})!"
                ]);
            }

            // Redirect to appropriate index page based on user role
            $redirectRoute = $user->roles->contains('name', 'admin') ? 'admin.archives.index' : 'intern.archives.index';

            return redirect()->route($redirectRoute)->with('success', "✅ Berhasil menghapus arsip ({$archiveNumber})!");
        } catch (\Exception $e) {
            Log::error('Archive deletion error: ' . $e->getMessage());

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Gagal menghapus arsip. Silakan coba lagi.'
                ], 500);
            }

            // Redirect to appropriate index page even on error
            $redirectRoute = $user->roles->contains('name', 'admin') ? 'admin.archives.index' : ($user->roles->contains('name', 'staff') ? 'staff.archives.index' : 'intern.archives.index');

            return redirect()->route($redirectRoute)->with('error', '❌ Gagal menghapus arsip. Silakan coba lagi.');
        }
    }

    /**
     * Export archives to Excel based on status
     */
    public function exportArchives($status = 'all', Request $request)
    {
        try {
            // Map status to proper format
            $mappedStatus = match ($status) {
                'aktif' => 'Aktif',
                'inaktif' => 'Inaktif',
                'musnah' => 'Musnah',
                'all' => 'all',
                default => 'all'
            };

            $statusTitle = $this->getStatusTitle($mappedStatus);
            $fileName = 'daftar-arsip-' . strtolower(str_replace(' ', '-', $statusTitle)) . '-' . date('Y-m-d') . '.xlsx';

            return Excel::download(new ArchiveExportWithHeader($mappedStatus), $fileName);
        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Gagal mengeksport data: ' . $e->getMessage()]);
        }
    }

    /**
     * Show export menu with status selection
     */
    public function exportMenu()
    {
        $user = Auth::user();
        $statuses = [
            'all' => 'Semua Status',
            'aktif' => 'Arsip Aktif',
            'inaktif' => 'Arsip Inaktif',
            'permanen' => 'Arsip Permanen',
            'musnah' => 'Arsip Musnah'
        ];

        // Count archives based on user role
        if ($user->roles->contains('name', 'intern')) {
            // Intern can only see their own archives
            $archiveCounts = [
                'all' => Archive::where('created_by', $user->id)->count(),
                'aktif' => Archive::where('created_by', $user->id)->where('status', 'Aktif')->count(),
                'inaktif' => Archive::where('created_by', $user->id)->where('status', 'Inaktif')->count(),
                'permanen' => Archive::where('created_by', $user->id)->where('status', 'Permanen')->count(),
                'musnah' => Archive::where('created_by', $user->id)->where('status', 'Musnah')->count(),
            ];
        } else {
            // Admin can see all archives
            $archiveCounts = [
                'all' => Archive::count(),
                'aktif' => Archive::aktif()->count(),
                'inaktif' => Archive::inaktif()->count(),
                'permanen' => Archive::permanen()->count(),
                'musnah' => Archive::musnah()->count(),
            ];
        }

        $viewPath = $this->getViewPath('archives.export-menu');
        return view($viewPath, compact('statuses', 'archiveCounts'));
    }

    /**
     * Show export all form with comprehensive filters
     */
    public function exportAllForm()
    {
        $categories = Category::orderBy('nama_kategori')->get();
        $classifications = Classification::with('category')->orderBy('nama_klasifikasi')->get();
        $users = \App\Models\User::orderBy('name')->get();
        $statuses = ['Aktif', 'Inaktif', 'Permanen', 'Musnah'];

        return view('admin.archives.export-all', compact('categories', 'classifications', 'users', 'statuses'));
    }

    /**
     * Show export form with filters
     */
    public function exportForm($status = 'all')
    {
        $statusTitle = $this->getStatusTitle($status);
        $user = Auth::user();

        // For intern, calculate total records they created with the specified status
        $totalRecords = 0;
        if ($user->roles->contains('name', 'intern')) {
            $query = Archive::where('created_by', $user->id);

            if ($status !== 'all') {
                $query->where('status', ucfirst($status));
            }

            $totalRecords = $query->count();
        } else {
            // For admin/staff, get all records
            $query = Archive::query();

            if ($status !== 'all') {
                $query->where('status', ucfirst($status));
            }

            $totalRecords = $query->count();
        }

        $viewPath = $this->getViewPath('archives.export');
        return view($viewPath, compact('status', 'statusTitle', 'totalRecords'));
    }

    /**
     * Export archives to Excel
     */
    public function export(Request $request)
    {
        $request->validate([
            'status' => 'required|in:all,aktif,inaktif,permanen,musnah,Aktif,Inaktif,Permanen,Musnah',
            'year_from' => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'year_to' => 'nullable|integer|min:2000|max:' . (date('Y') + 1),
            'created_by' => 'nullable|string|max:50',
            'category_id' => 'nullable|exists:categories,id',
            'classification_id' => 'nullable|exists:classifications,id'
        ]);

        $status = $request->status;
        $yearFrom = $request->year_from;
        $yearTo = $request->year_to;
        $categoryId = $request->category_id;
        $classificationId = $request->classification_id;
        $createdBy = $request->created_by;
        $user = Auth::user();

        // Normalize status to proper case
        $status = match ($status) {
            'aktif' => 'Aktif',
            'inaktif' => 'Inaktif',
            'permanen' => 'Permanen',
            'musnah' => 'Musnah',
            'all' => 'all',
            default => $status
        };

        // Filter by user role
        if ($user->roles->contains('name', 'intern')) {
            $createdBy = $user->id;
        } else {
            if ($createdBy === 'current_user') {
                $createdBy = $user->id;
            }
        }

        if ($yearFrom && $yearTo && $yearFrom > $yearTo) {
            return redirect()->back()->withErrors(['year_range' => 'Tahun "Dari" tidak boleh lebih besar dari tahun "Sampai"']);
        }

        $statusTitle = $this->getStatusTitle($status);
        $fileName = 'daftar-arsip-' . strtolower(str_replace(' ', '-', $statusTitle));

        if ($createdBy) {
            if ($createdBy == Auth::id()) {
                $fileName .= '-saya';
            } else {
                $userModel = \App\Models\User::find($createdBy);
                if ($userModel) {
                    $fileName .= '-' . strtolower(str_replace(' ', '-', $userModel->name));
                }
            }
        }

        if ($yearFrom && $yearTo) {
            if ($yearFrom == $yearTo) {
                $fileName .= '-' . $yearFrom;
            } else {
                $fileName .= '-' . $yearFrom . '-' . $yearTo;
            }
        } elseif ($yearFrom) {
            $fileName .= '-dari-' . $yearFrom;
        } elseif ($yearTo) {
            $fileName .= '-sampai-' . $yearTo;
        }

        $fileName .= '-' . date('Y-m-d') . '.xlsx';

        // Pilih kelas ekspor berdasarkan status
        if ($status === 'Aktif') {
            return Excel::download(
                new ArchiveAktifExport($yearFrom, $yearTo, $createdBy, $categoryId, $classificationId),
                $fileName
            );
        } elseif ($status === 'Musnah') {
            return Excel::download(
                new ArchiveMusnahExport($yearFrom, $yearTo, $createdBy, $categoryId, $classificationId),
                $fileName
            );
        } elseif ($status === 'Permanen') {
            return Excel::download(
                new ArchiveInaktifPermanenExport($status, $yearFrom, $yearTo, $createdBy, $categoryId, $classificationId),
                $fileName
            );
        } elseif ($status === 'Inaktif') {
            return Excel::download(
                new ArchiveInaktifPermanenExport($status, $yearFrom, $yearTo, $createdBy, $categoryId, $classificationId),
                $fileName
            );
        } else {
            // Untuk semua status
            return Excel::download(
                new ArchiveStatusExport($status, $yearFrom, $yearTo, $createdBy, $categoryId, $classificationId),
                $fileName
            );
        }
    }

    /**
     * Get status title for display
     */
    private function getStatusTitle($status): string
    {
        return match ($status) {
            'aktif', 'Aktif' => 'Aktif',
            'inaktif', 'Inaktif' => 'Inaktif',
            'permanen', 'Permanen' => 'Permanen',
            'musnah', 'Musnah' => 'Usul Musnah',
            'all' => 'Semua Status',
            default => 'Semua Status'
        };
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters($query, Request $request)
    {
        // Search filter
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('index_number', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhereHas('category', function ($catQuery) use ($searchTerm) {
                        $catQuery->where('nama_kategori', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('classification', function ($classQuery) use ($searchTerm) {
                        $classQuery->where('nama_klasifikasi', 'like', "%{$searchTerm}%")
                            ->orWhere('code', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Category filter
        if ($request->filled('category_filter')) {
            $query->where('category_id', $request->get('category_filter'));
        }

        // Classification filter
        if ($request->filled('classification_filter')) {
            $query->where('classification_id', $request->get('classification_filter'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('kurun_waktu_start', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('kurun_waktu_start', '<=', $request->get('date_to'));
        }

        // Filter by created_by
        if ($request->filled('created_by_filter')) {
            $query->where('created_by', $request->get('created_by_filter'));
        }

        return $query;
    }


    /**
     * Check if user can create archives
     */
    private function canCreateArchive(): bool
    {
        $user = Auth::user();
        return $user->roles->contains('name', 'admin') || $user->roles->contains('name', 'staff') || $user->roles->contains('name', 'intern');
    }

    /**
     * Get users for filter based on current user role
     */
    private function getFilterUsers()
    {
        $user = Auth::user();

        if ($user->roles->contains('name', 'admin')) {
            // Admin can see all users
            return \App\Models\User::orderBy('name')->get();
        } elseif ($user->roles->contains('name', 'staff')) {
            // Staff can only see staff and intern users
            return \App\Models\User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['staff', 'intern']);
            })->orderBy('name')->get();
        } elseif ($user->roles->contains('name', 'intern')) {
            // Intern can only see staff and intern users
            return \App\Models\User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['staff', 'intern']);
            })->orderBy('name')->get();
        }

        return \App\Models\User::orderBy('name')->get();
    }

    /**
     * Get the appropriate view path based on user role.
     */
    private function getViewPath(string $viewName): string
    {
        $user = Auth::user();

        if ($user->roles->contains('name', 'admin')) {
            return 'admin.' . $viewName;
        } elseif ($user->roles->contains('name', 'staff')) {
            return 'staff.' . $viewName;
        } elseif ($user->roles->contains('name', 'intern')) {
            return 'intern.' . $viewName;
        }

        return 'admin.' . $viewName; // Fallback to admin view
    }

}
