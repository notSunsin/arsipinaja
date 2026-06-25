<?php

    namespace App\Exports;

    use App\Models\Archive;
    use Maatwebsite\Excel\Concerns\FromCollection;
    use Maatwebsite\Excel\Concerns\WithMultipleSheets;
    use Maatwebsite\Excel\Concerns\WithTitle;
    use Maatwebsite\Excel\Concerns\WithEvents;
    use Maatwebsite\Excel\Concerns\WithStyles;
    use Maatwebsite\Excel\Events\AfterSheet;
    use Illuminate\Support\Facades\Storage;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    use PhpOffice\PhpSpreadsheet\Style\Font;
    use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

    class ArchiveStatusExport implements WithMultipleSheets
    {
        protected $status;
        protected $yearFrom;
        protected $yearTo;
        protected $createdBy;
        protected $categoryId;
        protected $classificationId;

        public function __construct($status, $yearFrom = null, $yearTo = null, $createdBy = null, $categoryId = null, $classificationId = null)
        {
            $this->status = $status;
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
                $sheets[] = new ArchiveStatusSheet(
                    $this->status,
                    null,
                    $this->createdBy,
                    $this->categoryId,
                    $this->classificationId
                );
                return $sheets;
            }

            // Untuk tahun tertentu
            $startYear = $this->yearFrom ?: Archive::min('kurun_waktu_start');
            $endYear = $this->yearTo ?: Archive::max('kurun_waktu_start');

            // Konversi Carbon ke tahun jika perlu
            if ($startYear instanceof \Carbon\Carbon) {
                $startYear = $startYear->year;
            }

            if ($endYear instanceof \Carbon\Carbon) {
                $endYear = $endYear->year;
            }

            for ($year = $startYear; $year <= $endYear; $year++) {
                $sheets[] = new ArchiveStatusSheet(
                    $this->status,
                    $year,
                    $this->createdBy,
                    $this->categoryId,
                    $this->classificationId
                );
            }

            return $sheets;
        }
    }

    class ArchiveStatusSheet implements FromCollection, WithTitle, WithEvents, WithStyles
    {
        protected $status;
        protected $year;
        protected $createdBy;
        protected $categoryId;
        protected $classificationId;

        public function __construct($status, $year = null, $createdBy = null, $categoryId = null, $classificationId = null)
        {
            $this->status = $status;
            $this->year = $year;
            $this->createdBy = $createdBy;
            $this->categoryId = $categoryId;
            $this->classificationId = $classificationId;
        }

        public function collection()
        {
            $query = Archive::with([
                'classification' => function($q) {
                    $q->select('id', 'code', 'nama_klasifikasi', 'retention_aktif', 'nasib_akhir');
                }
            ]);

            // Filter status - hanya jika bukan 'all'
            if ($this->status !== 'all') {
                $query->where('status', $this->status);
            }

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

            return $query->orderBy('created_at', 'asc')->get();
        }

        public function title(): string
        {
            return $this->year ? "TAHUN {$this->year}" : "SEMUA TAHUN";
        }

        public function styles(Worksheet $sheet)
        {
            // Set default style for all cells
            $sheet->getStyle('A:L')->applyFromArray([
                'font' => [
                    'name' => 'Arial',
                    'size' => 10,
                ],
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Hide columns after L
            foreach (range('M', 'Z') as $col) {
                $sheet->getColumnDimension($col)->setWidth(0);
                $sheet->getColumnDimension($col)->setVisible(false);
            }
        }

        public function registerEvents(): array
        {
            return [
                AfterSheet::class => function(AfterSheet $event) {
                    $sheet = $event->sheet->getDelegate();
                    $data = $this->collection();
                    $year = $this->year;

                    // 1. CLEAR ALL CELLS AFTER COLUMN L
                    $highestRow = $sheet->getHighestRow();
                    for ($row = 1; $row <= $highestRow; $row++) {
                        for ($col = 'M'; $col <= 'Z'; $col++) {
                            $sheet->setCellValue($col.$row, null);
                        }
                    }

                    // 2. SET WHITE BACKGROUND FOR AREA AFTER L
                    $sheet->getStyle('M1:Z'.$highestRow)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF']
                        ]
                    ]);

                    // 3. MAIN HEADERS
                    $headers = [
                        ['DAFTAR ARSIP ' . strtoupper($this->status), 'A1:L1'],
                        ['DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU', 'A2:L2'],
                        ['PROVINSI JAWA TIMUR', 'A3:L3'],
                        ['ISI Bagian....', 'A4:L4'],
                        [$year ? "TAHUN {$year}" : "SEMUA TAHUN", 'A5:L5'],
                        [strtoupper($this->status), 'A6:L6']
                    ];

                    foreach ($headers as $header) {
                        $sheet->setCellValue('A' . substr($header[1], 1, 1), $header[0]);
                        $sheet->mergeCells($header[1]);
                    }

                    // Style Header Utama (Baris 1-6)
                    $event->sheet->getStyle('A1:L6')->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 12,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'B3E5FC']
                        ],
                        'borders' => [
                            'outline' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '000000']
                            ],
                            'inside' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ]
                    ]);

                    // Khusus untuk Sub Bagian PTSP (Baris 4)
                    $event->sheet->getStyle('A4:J4')->getFont()->setItalic(true);
                    $event->sheet->getStyle('A4:J4')->getFont()->setBold(false);

                    // 4. COLUMN HEADERS (Baris 7-8)
                    $columnHeaders = [
                        ['No.', 'A7:A8'],
                        ['Kode Klasifikasi', 'B7:B8'],
                        ['Indeks', 'C7:C8'],
                        ['Uraian', 'D7:D8'],
                        ['Kurun Waktu', 'E7:E8'],
                        ['Tingkat Perkembangan', 'F7:F8'],
                        ['Jumlah', 'G7:G8'],
                        ['Ket.', 'H7:H8'],
                        ['Nomor Definitif', 'I7:I8'],
                        ['Jangka Simpan dan Nasib Akhir', 'J7:J8'],
                        ['Tembusan', 'K7:K8'],
                        ['File Arsip', 'L7:L8']
                    ];

                    foreach ($columnHeaders as $header) {
                        $sheet->setCellValue(substr($header[1], 0, 2), $header[0]);
                        $sheet->mergeCells($header[1]);
                    }

                    // Style Header Kolom dengan border
                    $event->sheet->getStyle('A7:L8')->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => 'FFFFFF']
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '4472C4']
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);

                    // 5. DATA CONTENT
                    $row = 9;
                    $nomorDefinitif = 1;
                    foreach ($data as $index => $archive) {
                        $sheet->setCellValue('A'.$row, $index + 1);
                        $sheet->setCellValue('B'.$row, $archive->classification->code ?? '-');
                        $sheet->setCellValue('C'.$row, $archive->lampiran_surat ?? '-');
                        $sheet->setCellValue('D'.$row, $archive->description ?? '-');
                        $sheet->setCellValue('E'.$row, $archive->kurun_waktu_start ? $archive->kurun_waktu_start->format('Y') : '-');
                        $sheet->setCellValue('F'.$row, $archive->tingkat_perkembangan ?? '-');
                        $sheet->setCellValue('G'.$row, $archive->jumlah_berkas ?? '-');
                        $sheet->setCellValue('H'.$row, $archive->ket ?? '(tidak ada keterangan)');
                        $sheet->setCellValue('I'.$row, $nomorDefinitif);
                        $jangkaSimpan = ($archive->classification->retention_aktif ?? 0) . ' Tahun';
                        $nasibAkhir = $archive->classification->nasib_akhir ?? 'Musnah';
                        $sheet->setCellValue('J'.$row, $jangkaSimpan . ' (' . $nasibAkhir . ')');

                        $tembusan = !empty($archive->tembusan) ? implode(', ', $archive->tembusan) : 'Tidak Ada Tembusan';
                        $sheet->setCellValue('K'.$row, $tembusan);

                        if ($archive->file_path) {
                            $fileUrl = url(Storage::disk('public')->url($archive->file_path));
                            $sheet->setCellValue('L'.$row, 'Lihat File');
                            $sheet->getCell('L'.$row)->getHyperlink()->setUrl($fileUrl);
                            $sheet->getStyle('L'.$row)->getFont()->setUnderline(true)->getColor()->setRGB('0563C1');
                        } else {
                            $sheet->setCellValue('L'.$row, '-');
                        }

                        $row++;
                        $nomorDefinitif++;
                    }

                    // Style Data dengan border
                    $lastRow = max($row - 1, 9);
                    $event->sheet->getStyle('A9:L'.$lastRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER
                        ]
                    ]);

                    // Alignment khusus
                    $event->sheet->getStyle('A9:A'.$lastRow)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $event->sheet->getStyle('E9:J'.$lastRow)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $event->sheet->getStyle('L9:L'.$lastRow)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // 6. SET COLUMN WIDTHS
                    $columnWidths = [
                        'A' => 5,   'B' => 15,  'C' => 25,
                        'D' => 40,  'E' => 15,  'F' => 20,
                        'G' => 10,  'H' => 15,  'I' => 20,
                        'J' => 30,  'K' => 30,  'L' => 15
                    ];

                    foreach ($columnWidths as $col => $width) {
                        $sheet->getColumnDimension($col)->setWidth($width);
                    }

                    // 7. SET ROW HEIGHTS
                    for ($i = 1; $i <= 8; $i++) {
                        $sheet->getRowDimension($i)->setRowHeight(25);
                    }

                    // 8. ADD RIGHT BORDER TO COLUMN L
                    $event->sheet->getStyle('L1:L'.$lastRow)->applyFromArray([
                        'borders' => [
                            'right' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '000000']
                            ]
                        ]
                    ]);
                }
            ];
        }
    }
