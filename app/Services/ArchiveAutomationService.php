<?php

namespace App\Services;

use App\Models\Archive;
use App\Models\Classification;
use Carbon\Carbon;

class ArchiveAutomationService
{
    /**
     * Auto-process archive after creation/update
     */
    public function autoProcessArchive(Archive $archive)
    {
        // 1. Auto-detect year
        $year = $archive->kurun_waktu_start->year;
        $archive->update(['year_detected' => $year]);

        // 2. Auto-sort by year (oldest first)
        $this->autoSortByYear($archive);

        // 3. Auto-generate definitive number per tahun
        $this->generateDefinitiveNumber($archive);
    }

    /**
     * Auto-sort archives by year (oldest first)
     */
    public function autoSortByYear(Archive $archive)
    {
        $year = $archive->kurun_waktu_start->year;

        // Get all archives with same classification, sorted by year
        $relatedArchives = Archive::where('classification_id', $archive->classification_id)
            ->orderBy('kurun_waktu_start')
            ->get();

        // Calculate sort order based on year
        $sortOrder = $relatedArchives->where('kurun_waktu_start', '<=', $archive->kurun_waktu_start)->count();

        $archive->update(['sort_order' => $sortOrder]);
    }

    /**
     * Generate definitive number (simple sequential number per classification and year)
     */
    public function generateDefinitiveNumber(Archive $archive)
    {
        $classificationId = $archive->classification_id;
        $year = $archive->kurun_waktu_start->year;

        $definitiveNumber = Archive::where('classification_id', $classificationId)
            ->whereYear('kurun_waktu_start', $year)
            ->where('id', '<=', $archive->id)
            ->count();

        $archive->update(['definitive_number' => $definitiveNumber]);
    }
}
