<?php

namespace App\Exports;

use App\Models\Archive;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ArchiveAktifExport implements WithMultipleSheets
{
    protected $yearFrom;
    protected $yearTo;
    protected $createdBy;
    protected $categoryId;
    protected $classificationId;

    public function __construct($yearFrom = null, $yearTo = null, $createdBy = null, $categoryId = null, $classificationId = null)
    {
        $this->yearFrom = $yearFrom;
        $this->yearTo = $yearTo;
        $this->createdBy = $createdBy;
        $this->categoryId = $categoryId;
        $this->classificationId = $classificationId;
    }

    public function sheets(): array
    {
        $sheets = [];

        if (!$this->yearFrom && !$this->yearTo) {
            $sheets[] = new ArchiveAktifSheet(null, $this->createdBy, $this->categoryId, $this->classificationId);
            return $sheets;
        }

        $startYear = $this->yearFrom ?: Archive::min('kurun_waktu_start');
        $endYear = $this->yearTo ?: Archive::max('kurun_waktu_start');

        if ($startYear instanceof \Carbon\Carbon) {
            $startYear = $startYear->year;
        }

        if ($endYear instanceof \Carbon\Carbon) {
            $endYear = $endYear->year;
        }

        for ($year = $startYear; $year <= $endYear; $year++) {
            $sheets[] = new ArchiveAktifSheet($year, $this->createdBy, $this->categoryId, $this->classificationId);
        }

        return $sheets;
    }
}

class ArchiveAktifSheet implements FromCollection, WithTitle, WithEvents
{
    protected $year;
    protected $createdBy;
    protected $categoryId;
    protected $classificationId;

    public function __construct($year = null, $createdBy = null, $categoryId = null, $classificationId = null)
    {
        $this->year = $year;
        $this->createdBy = $createdBy;
        $this->categoryId = $categoryId;
        $this->classificationId = $classificationId;
    }

    public function collection()
    {
        $query = Archive::with(['classification' => function($q) {
            $q->select('id', 'code', 'nama_klasifikasi'); // Hapus 'keterangan' karena tidak ada di tabel
        }])->where('status', 'Aktif');

        if ($this->year) {
            $query->whereYear('kurun_waktu_start', $this->year);
        }

        if ($this->createdBy) {
            $query->where('created_by', $this->createdBy);
        }
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }
        if ($this->classificationId) {
            $query->where('classification_id', $this->classificationId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function title(): string
    {
        return $this->year ? "TAHUN {$this->year}" : "SEMUA TAHUN";
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $data = $this->collection();

                // Header Utama
                $sheet->setCellValue('A1', 'DAFTAR BERKAS AKTIF');
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'B3E5FC']
                    ],
                    'borders' => [
                        'outline' => ['borderStyle' => Border::BORDER_MEDIUM]
                    ]
                ]);

                // Header Baris 2
                $headersRow2 = [
                    'NO',
                    'NO. BERKAS',
                    'KODE KLASIFIKASI DAN INDEKS',
                    'URAIAN INFORMASI',
                    'KURUN WAKTU',
                    'JUMLAH',
                    'SKKAD',
                    'TEMBUSAN',
                    'FILE ARSIP',
                ];

                foreach ($headersRow2 as $key => $header) {
                    $cell = chr(65 + $key) . '2';
                    $sheet->setCellValue($cell, $header);
                }

                // Merge vertikal kolom A–I
                foreach (range('A', 'I') as $col) {
                    $sheet->mergeCells("{$col}2:{$col}3");
                }

                // Style untuk Header (A2:I3)
                $sheet->getStyle('A2:I3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '4472C4']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);

                // Data dimulai dari baris 4
                $row = 4;
                foreach ($data as $index => $archive) {
                    $sheet->setCellValue('A'.$row, $index + 1);
                    $sheet->setCellValue('B'.$row, $archive->index_number ?? '-');

                    // Perbaikan: Gunakan nama_klasifikasi saja karena keterangan tidak ada
                    $kodeIndeks = ($archive->classification->code ?? '-') . ' - ' .
                                 ($archive->lampiran_surat ?? '-');
                    $sheet->setCellValue('C'.$row, $kodeIndeks);

                    $sheet->setCellValue('D'.$row, $archive->description ?? '-');
                    $sheet->setCellValue('E'.$row, $archive->kurun_waktu_start ? $archive->kurun_waktu_start->format('Y') : '-');
                    $sheet->setCellValue('F'.$row, $archive->jumlah_berkas ?? '-');
                    $sheet->setCellValue('G'.$row, $archive->skkad ?? '-');

                    $tembusan = !empty($archive->tembusan) ? implode(', ', $archive->tembusan) : 'Tidak Ada Tembusan';
                    $sheet->setCellValue('H'.$row, $tembusan);

                    if ($archive->file_path) {
                        $fileUrl = url(Storage::disk('public')->url($archive->file_path));
                        $sheet->setCellValue('I'.$row, 'Lihat File');
                        $sheet->getCell('I'.$row)->getHyperlink()->setUrl($fileUrl);
                        $sheet->getStyle('I'.$row)->getFont()->setUnderline(true)->getColor()->setRGB('0563C1');
                    } else {
                        $sheet->setCellValue('I'.$row, '-');
                    }

                    $row++;
                }

                // Styling data
                $lastRow = $row - 1;
                if ($lastRow >= 4) {
                    $sheet->getStyle("A4:I{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);

                    // Horizontal alignment
                    $sheet->getStyle("A4:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F4:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I4:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(20);
                $sheet->getColumnDimension('C')->setWidth(30);
                $sheet->getColumnDimension('D')->setWidth(40);
                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(15);
                $sheet->getColumnDimension('H')->setWidth(30);
                $sheet->getColumnDimension('I')->setWidth(15);

                // Row heights
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(25);
                $sheet->getRowDimension(3)->setRowHeight(25);

                // Hide columns after I
                foreach (range('J', 'Z') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(0);
                    $sheet->getColumnDimension($col)->setVisible(false);
                }

                // Clear all cells after column I
                $highestRow = $sheet->getHighestRow();
                for ($r = 1; $r <= $highestRow; $r++) {
                    for ($col = 'J'; $col <= 'Z'; $col++) {
                        $sheet->setCellValue($col.$r, null);
                    }
                }

                // Set white background for area after I
                $sheet->getStyle('J1:Z'.$highestRow)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF']
                    ]
                ]);
            }
        ];
    }
}
