<?php

namespace App\Services\Schedule;

use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ScheduleExcelImportService
{
    public const DAY_MAP = [
        'pazartesi' => 0, 'monday' => 0, 'mon' => 0, '1' => 0,
        'sali' => 1, 'salı' => 1, 'tuesday' => 1, 'tue' => 1, '2' => 1,
        'carsamba' => 2, 'çarşamba' => 2, 'wednesday' => 2, 'wed' => 2, '3' => 2,
        'persembe' => 3, 'perşembe' => 3, 'thursday' => 3, 'thu' => 3, '4' => 3,
        'cuma' => 4, 'friday' => 4, 'fri' => 4, '5' => 4,
        'cumartesi' => 5, 'saturday' => 5, 'sat' => 5, '6' => 5,
        'pazar' => 6, 'sunday' => 6, 'sun' => 6, '7' => 6,
    ];

    public const DAYS_ORDERED = [
        0 => 'Pazartesi',
        1 => 'Salı',
        2 => 'Çarşamba',
        3 => 'Perşembe',
        4 => 'Cuma',
        5 => 'Cumartesi',
        6 => 'Pazar',
    ];

    public const BROADCAST_TYPES = [
        'normal' => ['is_live' => false, 'is_repeat' => false, 'is_package' => false, 'note' => null],
        'normal yayın' => ['is_live' => false, 'is_repeat' => false, 'is_package' => false, 'note' => null],
        'tekrar' => ['is_live' => false, 'is_repeat' => true, 'is_package' => false, 'note' => null],
        'tekrar yayın' => ['is_live' => false, 'is_repeat' => true, 'is_package' => false, 'note' => null],
        'canlı' => ['is_live' => true, 'is_repeat' => false, 'is_package' => false, 'note' => null],
        'canlı yayın' => ['is_live' => true, 'is_repeat' => false, 'is_package' => false, 'note' => null],
        'canli' => ['is_live' => true, 'is_repeat' => false, 'is_package' => false, 'note' => null],
        'canli yayin' => ['is_live' => true, 'is_repeat' => false, 'is_package' => false, 'note' => null],
        'paket' => ['is_live' => false, 'is_repeat' => false, 'is_package' => true, 'note' => null],
        'paket yayın' => ['is_live' => false, 'is_repeat' => false, 'is_package' => true, 'note' => null],
        'özel' => ['is_live' => false, 'is_repeat' => false, 'is_package' => false, 'note' => 'Özel Yayın'],
        'özel yayın' => ['is_live' => false, 'is_repeat' => false, 'is_package' => false, 'note' => 'Özel Yayın'],
        'ozel' => ['is_live' => false, 'is_repeat' => false, 'is_package' => false, 'note' => 'Özel Yayın'],
        'ozel yayin' => ['is_live' => false, 'is_repeat' => false, 'is_package' => false, 'note' => 'Özel Yayın'],
    ];

    /**
     * Generates downloadable standard DOST TV Excel template.
     */
    public function generateSampleTemplate(): string
    {
        return $this->generateDostTvStandardTemplate();
    }

    /**
     * Generates the multi-day DOST TV standard Excel template.
     */
    public function generateDostTvStandardTemplate(): string
    {
        $spreadsheet = new Spreadsheet();

        // 1. Data Sheet (Programlar, Yayın Türleri & Hızlı Süre Seçenekleri)
        $dataSheet = $spreadsheet->createSheet();
        $dataSheet->setTitle('Programlar');

        // Fetch all programs regardless of status/archive, sorted Turkish A-Z
        $programs = Program::all()->pluck('name')->filter()->unique()->values()->all();
        usort($programs, function ($a, $b) {
            $trMap = [
                'ç' => 'c~', 'ğ' => 'g~', 'ı' => 'i~', 'i' => 'i~~', 'ö' => 'o~', 'ş' => 's~', 'ü' => 'u~',
                'Ç' => 'c~', 'Ğ' => 'g~', 'I' => 'i~', 'İ' => 'i~~', 'Ö' => 'o~', 'Ş' => 's~', 'Ü' => 'u~',
            ];
            $normA = strtr($a, $trMap);
            $normB = strtr($b, $trMap);

            return strcasecmp($normA, $normB);
        });

        if (empty($programs)) {
            $programs = ['Örnek Program 1', 'Örnek Program 2', 'Örnek Program 3'];
        }

        foreach ($programs as $idx => $pName) {
            $dataSheet->setCellValue('A' . ($idx + 1), $pName);
        }

        $types = ['CANLI', 'TEKRAR', 'PAKET'];
        foreach ($types as $idx => $tName) {
            $dataSheet->setCellValue('B' . ($idx + 1), $tName);
        }

        $dataSheet->getColumnDimension('A')->setAutoSize(true);
        $dataSheet->getColumnDimension('B')->setAutoSize(true);

        // 2. Main Schedule Sheet
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Yayın Akışı');

        // Top Banner
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'DOST TV YAYIN AKIŞI DÖNEM ŞABLONU');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // Metadata rows
        $metaStyleLabel = [
            'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
        ];

        $sheet->setCellValue('A3', 'Akış Adı *');
        $sheet->setCellValue('B3', '2026 Sonbahar Yayın Akışı');
        $sheet->getStyle('A3')->applyFromArray($metaStyleLabel);
        $sheet->getStyle('B3')->getFont()->setBold(true);

        // Real Excel DATE serial with dd.mm.yyyy format
        $firstDayOfMonth = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime(date('Y-m-01')));
        $lastDayOfYear = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime(date('Y-12-31')));

        $sheet->setCellValue('A4', 'Başlangıç Tarihi *');
        $sheet->setCellValue('B4', $firstDayOfMonth);
        $sheet->setCellValue('C4', '(Format: GG.AA.YYYY)');
        $sheet->getStyle('A4')->applyFromArray($metaStyleLabel);
        $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('dd.mm.yyyy');
        $sheet->getStyle('C4')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'))->setSize(9);

        $sheet->setCellValue('A5', 'Bitiş Tarihi *');
        $sheet->setCellValue('B5', $lastDayOfYear);
        $sheet->setCellValue('C5', '(Format: GG.AA.YYYY)');
        $sheet->getStyle('A5')->applyFromArray($metaStyleLabel);
        $sheet->getStyle('B5')->getNumberFormat()->setFormatCode('dd.mm.yyyy');
        $sheet->getStyle('C5')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'))->setSize(9);

        $sheet->getStyle('A3:B5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Quick Usage & Copy Guidance Panel (D3:E5)
        $sheet->mergeCells('D3:E5');
        $guideText = "💡 HIZLI KULLANIM & SAATLERİ KOPYALAMA REHBERİ\n" .
                     "• Tarih: GG.AA.YYYY formatında korunur (01.04.2026 gibi).\n" .
                     "• Saatler: Başlangıç ve Bitiş saatlerini yazın (örn: 08:37).\n" .
                     "• Otomatik Devam: Sonraki satırın başlangıcı önceki satırın bitişini alır.\n" .
                     "• Saat Kopyalama: Bir günün saatlerini seçip kopyalayarak (Ctrl+C) diğer güne yapıştırabilirsiniz (Ctrl+V).";
        $sheet->setCellValue('D3', $guideText);
        $sheet->getStyle('D3:E5')->applyFromArray([
            'font' => ['size' => 8.5, 'color' => ['rgb' => '334155']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        // Days Sections
        $currentRow = 7;
        $totalPrograms = count($programs);

        foreach (self::DAYS_ORDERED as $dayIndex => $dayName) {
            $dayUpper = mb_strtoupper($dayName, 'UTF-8');

            // Day Header
            $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $dayUpper);
            $sheet->setCellValue("E{$currentRow}", '24 Saat Kontrolü');

            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(24);

            // Columns Header
            $colHeaderRow = $currentRow + 1;
            $sheet->setCellValue("A{$colHeaderRow}", 'Başlangıç');
            $sheet->setCellValue("B{$colHeaderRow}", 'Bitiş');
            $sheet->setCellValue("C{$colHeaderRow}", 'Program');
            $sheet->setCellValue("D{$colHeaderRow}", 'Yayın Türü');
            $sheet->setCellValue("E{$colHeaderRow}", 'Not');

            $sheet->getStyle("A{$colHeaderRow}:E{$colHeaderRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $startDataRow = $colHeaderRow + 1;
            $endDataRow = $colHeaderRow + 25;

            // 25 Ready Rows per day
            for ($r = $startDataRow; $r <= $endDataRow; $r++) {
                if ($r === $startDataRow) {
                    $sheet->setCellValue("A{$r}", 0);
                } else {
                    $sheet->setCellValue("A{$r}", '=IF(B' . ($r - 1) . '<>"", B' . ($r - 1) . ', "")');
                }

                // Time number format for Start Time
                $sheet->getStyle("A{$r}")->getNumberFormat()->setFormatCode('hh:mm');
                $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Bitiş (End Time) formatted as hh:mm for direct manual entry
                $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('hh:mm');
                $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Program Dropdown
                $progValidation = $sheet->getCell("C{$r}")->getDataValidation();
                $progValidation->setType(DataValidation::TYPE_LIST);
                $progValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $progValidation->setAllowBlank(true);
                $progValidation->setShowDropDown(true);
                $progValidation->setFormula1('Programlar!$A$1:$A$' . $totalPrograms);

                // Yayın Türü Dropdown (CANLI, TEKRAR, PAKET)
                $typeValidation = $sheet->getCell("D{$r}")->getDataValidation();
                $typeValidation->setType(DataValidation::TYPE_LIST);
                $typeValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $typeValidation->setAllowBlank(true);
                $typeValidation->setShowDropDown(true);
                $typeValidation->setFormula1('Programlar!$B$1:$B$3');
                $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A{$r}:E{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
            }

            $currentRow = $endDataRow + 2; // spacer row between days
        }

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(36);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(26);

        $tempDir = sys_get_temp_dir();
        $filePath = $tempDir . '/DOST_TV_Yayin_Akisi_Sablonu_' . uniqid() . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Parses and validates uploaded Excel / CSV file supporting both standard DOST TV multi-day format and flat format.
     *
     * @param string|UploadedFile $file Input file path or UploadedFile
     * @return array Result summary and rows preview
     */
    public function parseAndValidate(string|UploadedFile $file): array
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! file_exists($realPath) || filesize($realPath) === 0) {
            return [
                'has_errors' => true,
                'total_count' => 0,
                'valid_count' => 0,
                'error_count' => 1,
                'general_error' => 'Dosya bulunamadı veya boş.',
                'period_name' => null,
                'valid_from' => null,
                'valid_until' => null,
                'valid_from_formatted' => null,
                'valid_until_formatted' => null,
                'days_summary' => [],
                'rows' => [],
                'errors' => [
                    [
                        'row_num' => '-',
                        'program_name' => '-',
                        'message' => 'Dosya bulunamadı veya boş.',
                    ],
                ],
            ];
        }

        try {
            $spreadsheet = IOFactory::load($realPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            Log::error('Excel load error: ' . $e->getMessage());
            return [
                'has_errors' => true,
                'total_count' => 0,
                'valid_count' => 0,
                'error_count' => 1,
                'general_error' => 'Excel dosyası okunamadı: ' . $e->getMessage(),
                'period_name' => null,
                'valid_from' => null,
                'valid_until' => null,
                'valid_from_formatted' => null,
                'valid_until_formatted' => null,
                'days_summary' => [],
                'rows' => [],
                'errors' => [
                    [
                        'row_num' => '-',
                        'program_name' => '-',
                        'message' => 'Excel dosyası okunamadı: ' . $e->getMessage(),
                    ],
                ],
            ];
        }

        if (empty($data)) {
            return [
                'has_errors' => true,
                'total_count' => 0,
                'valid_count' => 0,
                'error_count' => 1,
                'general_error' => 'Excel dosyası boş.',
                'period_name' => null,
                'valid_from' => null,
                'valid_until' => null,
                'valid_from_formatted' => null,
                'valid_until_formatted' => null,
                'days_summary' => [],
                'rows' => [],
                'errors' => [
                    [
                        'row_num' => '-',
                        'program_name' => '-',
                        'message' => 'Excel dosyası boş.',
                    ],
                ],
            ];
        }

        // Load all programs into memory (0 N+1)
        $programs = Program::all();
        $programLookup = [];
        foreach ($programs as $prog) {
            $rawName = trim($prog->name);
            $programLookup[$rawName] = $prog;
            $programLookup[mb_strtolower($rawName, 'UTF-8')] = $prog;
            $programLookup[$this->normalizeString($rawName)] = $prog;
        }

        // Check if file is DOST TV multi-day format or flat table format
        $isMultiDayFormat = $this->detectMultiDayFormat($data);

        if ($isMultiDayFormat) {
            return $this->parseMultiDayFormat($data, $programLookup);
        }

        return $this->parseFlatFormat($data, $programLookup);
    }

    /**
     * Parses the DOST TV multi-day format.
     */
    protected function parseMultiDayFormat(array $data, array $programLookup): array
    {
        $periodName = null;
        $validFrom = null;
        $validUntil = null;
        $errors = [];

        // 1. Extract Metadata from Top Rows
        foreach (array_slice($data, 0, 15, true) as $rowNum => $row) {
            $valA = $this->normalizeString((string) ($row['A'] ?? ''));
            $valB = trim((string) ($row['B'] ?? ''));

            if (str_contains($valA, 'akis adi') || str_contains($valA, 'donem adi') || str_contains($valA, 'akis') || str_contains($valA, 'donem')) {
                if (filled($valB) && blank($periodName)) {
                    $periodName = $valB;
                }
            } elseif (str_contains($valA, 'baslangic')) {
                if ($validFrom === null && (filled($valB) || filled($row['B'] ?? null))) {
                    $validFrom = $this->parseDate($valB ?: ($row['B'] ?? null));
                }
            } elseif (str_contains($valA, 'bitis')) {
                if ($validUntil === null && (filled($valB) || filled($row['B'] ?? null))) {
                    $validUntil = $this->parseDate($valB ?: ($row['B'] ?? null));
                }
            }
        }

        // Validate Required Metadata
        if (blank($periodName)) {
            $errors[] = [
                'row_num' => 'Üst Bilgi',
                'program_name' => '-',
                'message' => 'Akış Adı alanı zorunludur.',
            ];
        } else {
            // Check Duplicate Period Name
            if (ScheduleTemplate::where('name', $periodName)->exists()) {
                $errors[] = [
                    'row_num' => 'Üst Bilgi',
                    'program_name' => '-',
                    'message' => 'Bu isimde bir yayın dönemi zaten mevcut.',
                ];
            }
        }

        if (! $validFrom) {
            $errors[] = [
                'row_num' => 'Üst Bilgi',
                'program_name' => '-',
                'message' => 'Başlangıç Tarihi zorunludur ve geçerli bir formatta olmalıdır (Örn: 01.09.2026).',
            ];
        }

        if (! $validUntil) {
            $errors[] = [
                'row_num' => 'Üst Bilgi',
                'program_name' => '-',
                'message' => 'Bitiş Tarihi zorunludur ve geçerli bir formatta olmalıdır (Örn: 31.12.2026).',
            ];
        }

        if ($validFrom && $validUntil && $validUntil->lt($validFrom)) {
            $errors[] = [
                'row_num' => 'Üst Bilgi',
                'program_name' => '-',
                'message' => 'Bitiş tarihi başlangıç tarihinden önce olamaz.',
            ];
        }

        // 2. Parse Day Sections
        $currentDay = null;
        $dayRows = []; // [day_of_week => [rows]]
        $allProcessedRows = [];
        $warnings = [];
        $hasDurationColumn = false;

        foreach ($data as $rowNum => $row) {
            $colA = trim((string) ($row['A'] ?? ''));
            $colB = trim((string) ($row['B'] ?? ''));
            $colC = trim((string) ($row['C'] ?? ''));
            $colD = trim((string) ($row['D'] ?? ''));
            $colE = trim((string) ($row['E'] ?? ''));
            $colF = trim((string) ($row['F'] ?? ''));

            // Check if this row is a day header
            $normA = $this->normalizeString($colA);
            if (isset(self::DAY_MAP[$normA])) {
                $currentDay = self::DAY_MAP[$normA];
                continue;
            }

            // Check if this is a column header row
            if (str_contains($normA, 'baslangic') || str_contains($normA, 'start')) {
                $normB = $this->normalizeString($colB);
                if (str_contains($normB, 'sure') || str_contains($normB, 'duration')) {
                    $hasDurationColumn = true;
                } elseif (str_contains($normB, 'bitis') || str_contains($normB, 'end')) {
                    $hasDurationColumn = false;
                }
                continue;
            }

            // Skip metadata or info headers
            if (str_contains($normA, 'akis') || str_contains($normA, 'dost tv') || str_contains($normA, 'donem')) {
                continue;
            }

            if ($currentDay === null) {
                continue;
            }

            // Check completely empty row
            if ($this->isEmptyRow($row)) {
                continue;
            }

            // Extract row based on layout (with or without duration column)
            if ($hasDurationColumn) {
                $rawStart = $colA;
                $rawDuration = $colB;
                $rawEnd = $colC;
                $rawProgram = $colD;
                $rawType = $colE ?: 'Normal';
                $rawNote = $colF;
            } else {
                $rawStart = $colA;
                $rawDuration = null;
                $rawEnd = $colB;
                $rawProgram = $colC;
                $rawType = $colD ?: 'Normal';
                $rawNote = $colE;
            }

            // Ignore unused template placeholder rows (e.g. formula start 00:00 but no end, program or duration)
            if (empty($rawEnd) && empty($rawProgram) && empty($rawDuration)) {
                continue;
            }

            $rowErrors = [];
            $rowWarnings = [];

            // Calculate end time from duration if end time is missing but duration and start are given
            $startTimeFormatted = $this->formatTime($rawStart);
            $endTimeFormatted = $this->formatTime($rawEnd);

            if (! $endTimeFormatted && ! empty($rawDuration) && $startTimeFormatted) {
                $durationMinutes = match (trim($rawDuration)) {
                    '+15 dk', '15 dk', '15m', '15' => 15,
                    '+30 dk', '30 dk', '30m', '30' => 30,
                    '+45 dk', '45 dk', '45m', '45' => 45,
                    '+1 saat', '1 saat', '1s', '60 dk', '60' => 60,
                    '+1.5 saat', '1.5 saat', '90 dk', '90' => 90,
                    '+2 saat', '2 saat', '120 dk', '120' => 120,
                    default => null,
                };

                if ($durationMinutes !== null) {
                    try {
                        $cStart = Carbon::createFromFormat('H:i', $startTimeFormatted);
                        $cEnd = $cStart->copy()->addMinutes($durationMinutes);
                        $endTimeFormatted = $cEnd->format('H:i');
                        $rawEnd = $endTimeFormatted;
                    } catch (\Throwable $e) {
                    }
                }
            }

            // Partially filled check
            $filledFieldsCount = (! empty($rawStart) ? 1 : 0) + (! empty($rawEnd) ? 1 : 0) + (! empty($rawProgram) ? 1 : 0);
            if ($filledFieldsCount > 0 && $filledFieldsCount < 3) {
                $rowErrors[] = 'Satır eksik doldurulmuş. Başlangıç, Bitiş ve Program alanları zorunludur.';
            }

            if (! $startTimeFormatted && ! empty($rawStart)) {
                $rowErrors[] = "Geçersiz başlangıç saati: '{$rawStart}'";
            }
            if (! $endTimeFormatted && ! empty($rawEnd)) {
                $rowErrors[] = "Geçersiz bitiş saati: '{$rawEnd}'";
            }

            // Overnight and order check
            $isOvernight = false;
            if ($startTimeFormatted && $endTimeFormatted) {
                if (in_array($endTimeFormatted, ['00:00', '24:00'], true)) {
                    // Midnight end of day broadcast - always valid
                } elseif ($startTimeFormatted >= $endTimeFormatted) {
                    if ($startTimeFormatted >= '20:00' && $endTimeFormatted <= '06:00') {
                        $isOvernight = true;
                    } else {
                        $rowErrors[] = "Bitiş saati ({$endTimeFormatted}) başlangıç saatinden ({$startTimeFormatted}) sonra olmalıdır.";
                    }
                }
            }

            // Match Program (Tolerant with resolveProgram)
            $matchedProgram = null;
            if (empty($rawProgram)) {
                if ($filledFieldsCount > 0) {
                    $rowErrors[] = 'Program adı zorunludur.';
                }
            } else {
                $resolution = $this->resolveProgram($rawProgram, $programLookup);
                if ($resolution['program']) {
                    $matchedProgram = $resolution['program'];
                    if (! empty($resolution['warning'])) {
                        $rowWarnings[] = $resolution['warning'];
                    }
                } else {
                    $rowErrors[] = $resolution['error'] ?? "Program sistemde bulunamadı: '{$rawProgram}'";
                }
            }

            // Match Broadcast Type
            $typeNormalized = mb_strtolower(trim($rawType), 'UTF-8');
            $broadcastConfig = self::BROADCAST_TYPES[$typeNormalized] ?? null;
            if (! $broadcastConfig && ! empty($rawType)) {
                $rowErrors[] = "Geçersiz yayın türü: '{$rawType}' (Kabul edilen: CANLI, TEKRAR, PAKET)";
                $broadcastConfig = ['is_live' => false, 'is_repeat' => false, 'note' => null];
            } elseif (! $broadcastConfig) {
                $broadcastConfig = ['is_live' => false, 'is_repeat' => false, 'note' => null];
            }

            $finalNote = filled($rawNote) ? $rawNote : ($broadcastConfig['note'] ?? null);

            if (! empty($rowErrors)) {
                $status = 'error';
            } elseif (! empty($rowWarnings)) {
                $status = 'warning';
            } else {
                $status = 'ready';
            }

            $rowResult = [
                'row_num' => $rowNum,
                'raw_day' => self::DAYS_ORDERED[$currentDay],
                'day_of_week' => $currentDay,
                'day_name' => self::DAYS_ORDERED[$currentDay],
                'raw_start' => $rawStart,
                'start_time' => $startTimeFormatted,
                'raw_end' => $rawEnd,
                'end_time' => $endTimeFormatted,
                'raw_program' => $rawProgram,
                'program_id' => $matchedProgram?->id,
                'program_name' => $matchedProgram?->name ?? $rawProgram,
                'raw_type' => $rawType,
                'is_live' => $broadcastConfig['is_live'] ?? false,
                'is_repeat' => $broadcastConfig['is_repeat'] ?? false,
                'is_package' => $broadcastConfig['is_package'] ?? false,
                'note' => $finalNote,
                'is_active' => true,
                'is_overnight' => $isOvernight,
                'status' => $status,
                'errors' => $rowErrors,
                'warnings' => $rowWarnings,
            ];

            $dayRows[$currentDay][] = $rowResult;
            $allProcessedRows[] = $rowResult;

            if (! empty($rowWarnings)) {
                foreach ($rowWarnings as $warn) {
                    $warnings[] = [
                        'row_num' => "Satır {$rowNum} (" . self::DAYS_ORDERED[$currentDay] . ")",
                        'program_name' => $rawProgram ?: '-',
                        'matched_program' => $matchedProgram?->name ?? '-',
                        'message' => $warn,
                        'type' => 'WARNING',
                    ];
                }
            }

            if (! empty($rowErrors)) {
                foreach ($rowErrors as $err) {
                    $errors[] = [
                        'row_num' => "Satır {$rowNum} (" . self::DAYS_ORDERED[$currentDay] . ")",
                        'program_name' => $rawProgram ?: '-',
                        'message' => $err,
                        'type' => 'ERROR',
                    ];
                }
            }
        }

        // 3. Strict 24-Hour & Continuity Validation per Day
        $daysSummary = [];
        foreach (self::DAYS_ORDERED as $dayIndex => $dayName) {
            $rowsForDay = $dayRows[$dayIndex] ?? [];
            $dayErrors = [];

            if (empty($rowsForDay)) {
                $msg = "{$dayName} günü için hiç yayın girilmemiş. TV kanalı 24 saat yayın yapmak zorundadır.";
                $errors[] = [
                    'row_num' => $dayName,
                    'program_name' => '-',
                    'message' => $msg,
                    'type' => 'ERROR',
                ];
                $dayErrors[] = $msg;
                $daysSummary[$dayIndex] = [
                    'day_name' => $dayName,
                    'count' => 0,
                    'status' => 'error',
                    'errors' => $dayErrors,
                ];
                continue;
            }

            // Check first row starts at 00:00
            $firstRow = $rowsForDay[0];
            if ($firstRow['start_time'] !== '00:00') {
                $msg = "{$dayName} günü yayını 00:00'da başlamalıdır (Mevcut başlangıç: {$firstRow['start_time']}).";
                $errors[] = [
                    'row_num' => "Satır {$firstRow['row_num']} ({$dayName})",
                    'program_name' => $firstRow['program_name'],
                    'message' => $msg,
                    'type' => 'ERROR',
                ];
                $dayErrors[] = $msg;
            }

            // Continuity Check (Gap & Overlap)
            for ($i = 0; $i < count($rowsForDay) - 1; $i++) {
                $curr = $rowsForDay[$i];
                $next = $rowsForDay[$i + 1];

                if (! $curr['end_time'] || ! $next['start_time']) {
                    continue;
                }

                // If current row ends at 00:00 or is overnight, following row is invalid
                if (in_array($curr['end_time'], ['00:00', '24:00'], true) || $curr['is_overnight']) {
                    $msg = "{$dayName} gününde gün sonuna ulaşan yayından sonra yeni yayın satırı eklenemez.";
                    $errors[] = [
                        'row_num' => "Satır {$next['row_num']} ({$dayName})",
                        'program_name' => $next['program_name'],
                        'message' => $msg,
                        'type' => 'ERROR',
                    ];
                    $dayErrors[] = $msg;
                    break;
                }

                if ($next['start_time'] > $curr['end_time']) {
                    $msg = "Yayın boşluğu! {$dayName} gününde {$curr['end_time']} ile {$next['start_time']} arasında boşluk bulunmaktadır.";
                    $errors[] = [
                        'row_num' => "Satır {$next['row_num']} ({$dayName})",
                        'program_name' => $next['program_name'],
                        'message' => $msg,
                        'type' => 'ERROR',
                    ];
                    $dayErrors[] = $msg;
                } elseif ($next['start_time'] < $curr['end_time']) {
                    $msg = "Yayın çakışması! {$dayName} gününde {$next['start_time']} saatinde başlayan yayın, önceki {$curr['end_time']} bitişli yayınla çakışıyor.";
                    $errors[] = [
                        'row_num' => "Satır {$next['row_num']} ({$dayName})",
                        'program_name' => $next['program_name'],
                        'message' => $msg,
                        'type' => 'ERROR',
                    ];
                    $dayErrors[] = $msg;
                }
            }

            // Check last row completes at 00:00 / 24:00 or overnight
            $lastRow = end($rowsForDay);
            $lastEnd = $lastRow['end_time'] ?? '';
            $isComplete = in_array($lastEnd, ['00:00', '24:00'], true) || ($lastRow['is_overnight'] ?? false);

            if (! $isComplete) {
                $msg = "{$dayName} günü yayını 00:00'da tamamlanmamıştır (Mevcut bitiş: {$lastEnd}).";
                $errors[] = [
                    'row_num' => "Satır {$lastRow['row_num']} ({$dayName})",
                    'program_name' => $lastRow['program_name'],
                    'message' => $msg,
                    'type' => 'ERROR',
                ];
                $dayErrors[] = $msg;
            }

            $daysSummary[$dayIndex] = [
                'day_name' => $dayName,
                'count' => count($rowsForDay),
                'status' => empty($dayErrors) ? 'ready' : 'error',
                'errors' => $dayErrors,
            ];
        }

        $hasErrors = count($errors) > 0;
        $hasWarnings = count($warnings) > 0;
        $totalCount = count($allProcessedRows);
        $validCount = $hasErrors ? 0 : count(array_filter($allProcessedRows, fn ($r) => in_array($r['status'], ['ready', 'warning'], true)));
        $errorCount = count($errors);
        $warningCount = count($warnings);

        return [
            'has_errors' => $hasErrors,
            'has_warnings' => $hasWarnings,
            'total_count' => $totalCount,
            'valid_count' => $validCount,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'period_name' => $periodName,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'valid_from_formatted' => $validFrom ? $validFrom->format('d.m.Y') : null,
            'valid_until_formatted' => $validUntil ? $validUntil->format('d.m.Y') : null,
            'general_error' => $hasErrors ? ('Toplam ' . $errorCount . ' adet hata tespit edildi.') : null,
            'days_summary' => $daysSummary,
            'rows' => $allProcessedRows,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Parses legacy flat format (Gün, Başlangıç, Bitiş, Program...) for backward compatibility.
     */
    protected function parseFlatFormat(array $data, array $programLookup): array
    {
        $headerRow = array_shift($data);
        $colMap = $this->mapHeaders($headerRow);

        $missingHeaders = [];
        foreach (['day', 'start_time', 'end_time', 'program'] as $requiredKey) {
            if (! isset($colMap[$requiredKey])) {
                $missingHeaders[] = match ($requiredKey) {
                    'day' => 'Gün',
                    'start_time' => 'Başlangıç',
                    'end_time' => 'Bitiş',
                    'program' => 'Program',
                };
            }
        }

        if (! empty($missingHeaders)) {
            return [
                'has_errors' => true,
                'total_count' => 0,
                'valid_count' => 0,
                'error_count' => 1,
                'general_error' => 'Eksik zorunlu sütun(lar): ' . implode(', ', $missingHeaders),
                'period_name' => null,
                'valid_from' => null,
                'valid_until' => null,
                'valid_from_formatted' => null,
                'valid_until_formatted' => null,
                'days_summary' => [],
                'rows' => [],
                'errors' => [
                    [
                        'row_num' => 1,
                        'program_name' => '-',
                        'message' => 'Zorunlu sütunlar eksik: ' . implode(', ', $missingHeaders),
                        'type' => 'ERROR',
                    ],
                ],
                'warnings' => [],
            ];
        }

        $processedRows = [];
        $errors = [];
        $warnings = [];
        $seenKeys = [];
        $rowNum = 1;
        foreach ($data as $rawRow) {
            $rowNum++;
            if ($this->isEmptyRow($rawRow)) {
                continue;
            }

            $rawDay = trim((string) ($rawRow[$colMap['day']] ?? ''));
            $rawStart = trim((string) ($rawRow[$colMap['start_time']] ?? ''));
            $rawEnd = trim((string) ($rawRow[$colMap['end_time']] ?? ''));
            $rawProgram = trim((string) ($rawRow[$colMap['program']] ?? ''));
            $rawType = trim((string) ($rawRow[$colMap['type'] ?? ''] ?? 'Normal'));
            $rawActive = trim((string) ($rawRow[$colMap['is_active'] ?? ''] ?? 'Evet'));

            $rowErrors = [];
            $rowWarnings = [];

            $dayOfWeek = $this->parseDayOfWeek($rawDay);
            if ($dayOfWeek === null) {
                $rowErrors[] = "Geçersiz gün: '{$rawDay}' (Kabul edilen: Pazartesi-Pazar)";
            }

            $startTimeFormatted = $this->formatTime($rawStart);
            $endTimeFormatted = $this->formatTime($rawEnd);

            if (! $startTimeFormatted) {
                $rowErrors[] = "Geçersiz başlangıç saati: '{$rawStart}'";
            }
            if (! $endTimeFormatted) {
                $rowErrors[] = "Geçersiz bitiş saati: '{$rawEnd}'";
            }

            if ($startTimeFormatted && $endTimeFormatted) {
                if ($startTimeFormatted >= $endTimeFormatted) {
                    if ($startTimeFormatted >= '20:00' && ($endTimeFormatted <= '06:00' || $endTimeFormatted === '00:00')) {
                        // overnight broadcast
                    } else {
                        $rowErrors[] = "Bitiş saati ({$endTimeFormatted}) başlangıç saatinden ({$startTimeFormatted}) sonra olmalıdır.";
                    }
                }
            }

            $matchedProgram = null;
            if (empty($rawProgram)) {
                $rowErrors[] = 'Program adı zorunludur.';
            } else {
                $resolution = $this->resolveProgram($rawProgram, $programLookup);
                if ($resolution['program']) {
                    $matchedProgram = $resolution['program'];
                    if (! empty($resolution['warning'])) {
                        $rowWarnings[] = $resolution['warning'];
                    }
                } else {
                    $rowErrors[] = $resolution['error'] ?? "Bu program sistemde bulunamadı: '{$rawProgram}'";
                }
            }

            $typeNormalized = mb_strtolower($rawType, 'UTF-8');
            $broadcastConfig = self::BROADCAST_TYPES[$typeNormalized] ?? ['is_live' => false, 'is_repeat' => false, 'note' => null];

            $isActive = $this->parseBoolean($rawActive);

            $duplicateKey = "{$dayOfWeek}_{$startTimeFormatted}_{$endTimeFormatted}_" . ($matchedProgram?->id ?? $rawProgram);
            if (isset($seenKeys[$duplicateKey])) {
                $rowErrors[] = 'Bu satır daha önce eklenmiş (Aynı gün, saat ve program duplicate).';
            } else {
                $seenKeys[$duplicateKey] = true;
            }

            if ($dayOfWeek !== null && $startTimeFormatted && $endTimeFormatted) {
                if (! isset($daySchedules[$dayOfWeek])) {
                    $daySchedules[$dayOfWeek] = [];
                }

                foreach ($daySchedules[$dayOfWeek] as $existingItem) {
                    $eStart = $existingItem['start'];
                    $eEnd = $existingItem['end'];

                    if ($startTimeFormatted < $eEnd && $endTimeFormatted > $eStart) {
                        $rowErrors[] = "Yayın çakışması! {$rawDay} {$startTimeFormatted}-{$endTimeFormatted} aralığı, başka bir satırdaki {$eStart}-{$eEnd} yayınıyla çakışıyor.";
                        break;
                    }
                }

                if (empty($rowErrors)) {
                    $daySchedules[$dayOfWeek][] = [
                        'start' => $startTimeFormatted,
                        'end' => $endTimeFormatted,
                        'row' => $rowNum,
                    ];
                }
            }

            $status = empty($rowErrors) ? (empty($rowWarnings) ? 'ready' : 'warning') : 'error';

            $rowResult = [
                'row_num' => $rowNum,
                'raw_day' => $rawDay,
                'day_of_week' => $dayOfWeek,
                'day_name' => $dayOfWeek !== null ? (Schedule::DAYS[$dayOfWeek] ?? $rawDay) : $rawDay,
                'raw_start' => $rawStart,
                'start_time' => $startTimeFormatted,
                'raw_end' => $rawEnd,
                'end_time' => $endTimeFormatted,
                'raw_program' => $rawProgram,
                'program_id' => $matchedProgram?->id,
                'program_name' => $matchedProgram?->name ?? $rawProgram,
                'raw_type' => $rawType,
                'is_live' => $broadcastConfig['is_live'],
                'is_repeat' => $broadcastConfig['is_repeat'],
                'note' => $broadcastConfig['note'],
                'raw_active' => $rawActive,
                'is_active' => $isActive,
                'status' => $status,
                'errors' => $rowErrors,
                'warnings' => $rowWarnings,
            ];

            $processedRows[] = $rowResult;

            if (! empty($rowWarnings)) {
                foreach ($rowWarnings as $warn) {
                    $warnings[] = [
                        'row_num' => $rowNum,
                        'program_name' => $rawProgram ?: '-',
                        'matched_program' => $matchedProgram?->name ?? '-',
                        'message' => $warn,
                        'type' => 'WARNING',
                    ];
                }
            }

            if (! empty($rowErrors)) {
                foreach ($rowErrors as $err) {
                    $errors[] = [
                        'row_num' => $rowNum,
                        'program_name' => $rawProgram ?: '-',
                        'matched_program' => $matchedProgram?->name ?? '-',
                        'message' => $err,
                        'type' => 'ERROR',
                    ];
                }
            }
        }

        return [
            'has_errors' => count($errors) > 0,
            'has_warnings' => count($warnings) > 0,
            'total_count' => count($processedRows),
            'valid_count' => count(array_filter($processedRows, fn ($r) => in_array($r['status'], ['ready', 'warning'], true))),
            'error_count' => count($errors),
            'warning_count' => count($warnings),
            'period_name' => null,
            'valid_from' => null,
            'valid_until' => null,
            'valid_from_formatted' => null,
            'valid_until_formatted' => null,
            'days_summary' => [],
            'rows' => $processedRows,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Executes DB transaction to create ScheduleTemplate and its items.
     */
    public function importToTemplate(ScheduleTemplate $template, array $validRows): int
    {
        return DB::transaction(function () use ($template, $validRows) {
            $createdCount = 0;

            foreach ($validRows as $row) {
                if (! in_array($row['status'] ?? '', ['ready', 'warning'], true) || ! empty($row['errors'] ?? [])) {
                    continue;
                }

                ScheduleTemplateItem::create([
                    'schedule_template_id' => $template->id,
                    'program_id' => $row['program_id'],
                    'day_of_week' => $row['day_of_week'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'custom_title' => null,
                    'is_live' => $row['is_live'] ?? false,
                    'is_repeat' => $row['is_repeat'] ?? false,
                    'is_package' => $row['is_package'] ?? false,
                    'is_active' => $row['is_active'] ?? true,
                    'note' => $row['note'] ?? null,
                ]);

                $createdCount++;
            }

            return $createdCount;
        });
    }

    /**
     * Generates error & warning Excel report file for downloadable errors.
     */
    public function generateErrorExport(array $errors, array $warnings = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('İçe Aktarma Raporu');

        $headers = ['Tür', 'Excel Satır No', 'Program Adı', 'Eşleşen Program', 'Açıklama'];
        $sheet->fromArray([$headers], null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        $exportData = [];
        foreach ($errors as $err) {
            $exportData[] = [
                'HATA',
                $err['row_num'] ?? '-',
                $err['program_name'] ?? '-',
                $err['matched_program'] ?? '-',
                $err['message'] ?? '-',
            ];
        }

        foreach ($warnings as $warn) {
            $exportData[] = [
                'UYARI',
                $warn['row_num'] ?? '-',
                $warn['program_name'] ?? '-',
                $warn['matched_program'] ?? '-',
                $warn['message'] ?? '-',
            ];
        }

        if (empty($exportData)) {
            $exportData[] = ['BİLGİ', '-', '-', '-', 'Herhangi bir hata veya uyarı bulunamadı.'];
        }

        $sheet->fromArray($exportData, null, 'A2');

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tempDir = sys_get_temp_dir();
        $filePath = $tempDir . '/Yayın_Akışı_İçe_Aktarma_Raporu_' . uniqid() . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    protected function detectMultiDayFormat(array $data): bool
    {
        $firstRow = reset($data);
        if (is_array($firstRow)) {
            $rowValues = array_map(fn ($v) => $this->normalizeString((string) $v), $firstRow);
            if (in_array('gun', $rowValues, true) && in_array('program', $rowValues, true)) {
                return false;
            }
        }

        foreach (array_slice($data, 0, 15) as $row) {
            $colA = $this->normalizeString((string) ($row['A'] ?? ''));
            if (str_contains($colA, 'akis') || str_contains($colA, 'dost tv') || str_contains($colA, 'donem') || str_contains($colA, 'baslangic')) {
                return true;
            }
        }

        return false;
    }

    protected function parseDate(mixed $rawDate): ?Carbon
    {
        if (blank($rawDate)) {
            return null;
        }

        if ($rawDate instanceof \DateTimeInterface) {
            return Carbon::instance($rawDate)->startOfDay();
        }

        // Handle Excel numeric date serial (e.g. 46266 for 2026-09-01)
        if (is_numeric($rawDate) && (float) $rawDate > 1000) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $rawDate);
                return Carbon::instance($dt)->startOfDay();
            } catch (\Throwable $e) {
                // fallback to timestamp conversion
                try {
                    $unixDate = ($rawDate - 25569) * 86400;
                    return Carbon::createFromTimestampUTC((int) $unixDate)->startOfDay();
                } catch (\Throwable $e2) {
                    // fallback to string parse
                }
            }
        }

        $str = trim((string) $rawDate);
        $formats = ['d.m.Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd-m-Y', 'j.n.Y', 'j/n/Y'];
        foreach ($formats as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $str);
                if ($parsed && $parsed->year > 1900) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable $e) {
                // try next format
            }
        }

        try {
            return Carbon::parse($str)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $colLetter => $headerText) {
            if ($headerText === null) {
                continue;
            }

            $normalized = $this->normalizeString((string) $headerText);

            if (str_contains($normalized, 'gun') || str_contains($normalized, 'day')) {
                $map['day'] = $colLetter;
            } elseif (str_contains($normalized, 'baslangic') || str_contains($normalized, 'start')) {
                $map['start_time'] = $colLetter;
            } elseif (str_contains($normalized, 'bitis') || str_contains($normalized, 'end')) {
                $map['end_time'] = $colLetter;
            } elseif (str_contains($normalized, 'program')) {
                $map['program'] = $colLetter;
            } elseif (str_contains($normalized, 'tur') || str_contains($normalized, 'type')) {
                $map['type'] = $colLetter;
            } elseif (str_contains($normalized, 'aktif') || str_contains($normalized, 'active')) {
                $map['is_active'] = $colLetter;
            }
        }

        return $map;
    }

    protected function parseDayOfWeek(string $rawDay): ?int
    {
        $norm = $this->normalizeString($rawDay);

        return self::DAY_MAP[$norm] ?? null;
    }

    /**
     * Resolves program name with exact match first, and trailing parentheses tolerance fallback.
     */
    public function resolveProgram(string $rawProgram, array $programLookup): array
    {
        $raw = trim($rawProgram);
        $cleanRaw = preg_replace('/\s+/u', ' ', $raw);

        if (empty($cleanRaw)) {
            return [
                'program' => null,
                'is_exact' => false,
                'is_normalized' => false,
                'normalized_name' => null,
                'parenthesis_text' => null,
                'warning' => null,
                'error' => 'Program adı zorunludur.',
            ];
        }

        // 1. Try Exact match first with full cell text
        $exactProgram = $this->findProgram($cleanRaw, $programLookup);
        if ($exactProgram) {
            return [
                'program' => $exactProgram,
                'is_exact' => true,
                'is_normalized' => false,
                'normalized_name' => $cleanRaw,
                'parenthesis_text' => null,
                'warning' => null,
                'error' => null,
            ];
        }

        // 2. Try stripping trailing parenthesis: e.g. "Beraber Okuyalım (Lemalar)" -> "Beraber Okuyalım"
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $cleanRaw, $matches)) {
            $baseName = trim($matches[1]);
            $parenthesisText = trim($matches[2]);

            if (! empty($baseName)) {
                $normProgram = $this->findProgram($baseName, $programLookup);
                if ($normProgram) {
                    return [
                        'program' => $normProgram,
                        'is_exact' => false,
                        'is_normalized' => true,
                        'normalized_name' => $baseName,
                        'parenthesis_text' => $parenthesisText,
                        'warning' => "\"{$cleanRaw}\" adı, \"{$normProgram->name}\" programı ile eşleştirildi (Parantezli açıklama '{$parenthesisText}' yok sayıldı).",
                        'error' => null,
                    ];
                }

                return [
                    'program' => null,
                    'is_exact' => false,
                    'is_normalized' => true,
                    'normalized_name' => $baseName,
                    'parenthesis_text' => $parenthesisText,
                    'warning' => null,
                    'error' => "Program sistemde bulunamadı: '{$cleanRaw}' (Normalize edilen ad: '{$baseName}')",
                ];
            }
        }

        // 3. Program not found
        return [
            'program' => null,
            'is_exact' => false,
            'is_normalized' => false,
            'normalized_name' => $cleanRaw,
            'parenthesis_text' => null,
            'warning' => null,
            'error' => "Program sistemde bulunamadı: '{$cleanRaw}'",
        ];
    }

    protected function findProgram(string $rawProgram, array $programLookup): ?Program
    {
        $trimmed = trim($rawProgram);

        // 1. Exact match
        if (isset($programLookup[$trimmed])) {
            return $programLookup[$trimmed];
        }

        // 2. Case-insensitive lower
        $lower = mb_strtolower($trimmed, 'UTF-8');
        if (isset($programLookup[$lower])) {
            return $programLookup[$lower];
        }

        // 3. Normalized string match
        $norm = $this->normalizeString($trimmed);
        if (isset($programLookup[$norm])) {
            return $programLookup[$norm];
        }

        return null;
    }

    protected function formatTime(mixed $rawTime): ?string
    {
        if ($rawTime === null) {
            return null;
        }

        if (is_string($rawTime)) {
            $rawTime = trim($rawTime);
            if ($rawTime === '') {
                return null;
            }
        }

        // Exact strings for midnight / end-of-day
        if (in_array((string) $rawTime, ['00:00', '0:00', '24:00', '24:00:00', '00:00:00'], true)) {
            return '00:00';
        }

        // Numeric 0 or 1.0 (midnight)
        if (is_numeric($rawTime)) {
            $fVal = (float) $rawTime;
            if ($fVal == 0.0 || $fVal == 1.0) {
                return '00:00';
            }

            // Excel time serial (< 1.0)
            if ($fVal > 0.0 && $fVal < 1.0) {
                $totalSeconds = (int) round($fVal * 86400);
                $hours = (int) floor($totalSeconds / 3600);
                $minutes = (int) floor(($totalSeconds % 3600) / 60);

                if ($hours === 24) {
                    $hours = 0;
                }

                return sprintf('%02d:%02d', $hours, $minutes);
            }
        }

        // Standard string HH:MM or HH:MM:SS or HH.MM
        $str = (string) $rawTime;
        if (preg_match('/^(\d{1,2})[:\.](\d{2})(?:[:\.]\d{2})?$/', $str, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];

            if ($h >= 0 && $h <= 24 && $min >= 0 && $min < 60) {
                if ($h === 24 && $min === 0) {
                    return '00:00';
                }
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return null;
    }

    protected function parseBoolean(string $rawActive): bool
    {
        $norm = $this->normalizeString($rawActive);

        return in_array($norm, ['evet', '1', 'true', 'yes', 'aktif'], true);
    }

    protected function normalizeString(string $str): string
    {
        $str = str_replace(['İ', 'I', 'ı'], ['i', 'i', 'i'], $str);
        $str = mb_strtolower(trim($str), 'UTF-8');
        $trMap = [
            'ç' => 'c', 'ğ' => 'g', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
        ];

        return str_replace(array_keys($trMap), array_values($trMap), $str);
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $val) {
            if ($val !== null && trim((string) $val) !== '') {
                return false;
            }
        }

        return true;
    }
}
