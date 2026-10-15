<?php

namespace App\Services\Schedule;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleExcelExportService
{
    /**
     * Exports the selected schedule template to a 2-sheet Excel file.
     */
    public function exportTemplateToExcel(ScheduleTemplate $template, bool $includePassive = false): StreamedResponse
    {
        $spreadsheet = $this->createSpreadsheet($template, $includePassive);
        $filename = 'dost-tv-' . Str::slug($template->name ?: 'yayin-akisi') . '-haftalik.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Builds and returns the PhpSpreadsheet object.
     */
    public function createSpreadsheet(ScheduleTemplate $template, bool $includePassive = false): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // Fetch template items
        $query = $template->items()->with('program');
        if (! $includePassive) {
            $query->where('is_active', true);
        }

        $items = $query->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // 1. Sheet 1: Haftalık Yayın Akışı (Matrix View)
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Haftalık Yayın Akışı');

        // Banner Title Row
        $sheet1->mergeCells('A1:H1');
        $sheet1->setCellValue('A1', 'DOST TV YAYIN AKIŞI — ' . mb_strtoupper($template->name, 'UTF-8'));
        $sheet1->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet1->getRowDimension(1)->setRowHeight(32);

        // Header Row (Row 3)
        $headersSheet1 = ['Saat', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
        foreach ($headersSheet1 as $colIdx => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet1->setCellValue($colLetter . '3', $header);
        }

        $sheet1->getStyle('A3:H3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet1->getRowDimension(3)->setRowHeight(24);

        // Unique start times across all items
        $uniqueStartTimes = $items->map(function ($item) {
            return Carbon::parse($item->start_time)->format('H:i');
        })->unique()->sort()->values();

        $rowIndex = 4;

        if ($uniqueStartTimes->isEmpty()) {
            $sheet1->mergeCells('A4:H4');
            $sheet1->setCellValue('A4', 'Bu yayın akışında henüz kaydedilmiş bir yayın bulunmamaktadır.');
            $sheet1->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getRowDimension(4)->setRowHeight(30);
        } else {
            // Group items by start_time and day_of_week
            $groupedMap = [];
            foreach ($items as $item) {
                $startTimeFormatted = Carbon::parse($item->start_time)->format('H:i');
                $day = (int) $item->day_of_week;
                $groupedMap[$startTimeFormatted][$day][] = $item;
            }

            foreach ($uniqueStartTimes as $timeStr) {
                $sheet1->setCellValue('A' . $rowIndex, $timeStr);
                $sheet1->getStyle('A' . $rowIndex)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                for ($dayIdx = 0; $dayIdx < 7; $dayIdx++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dayIdx + 2); // B = Pazartesi
                    $dayItems = $groupedMap[$timeStr][$dayIdx] ?? [];

                    if (! empty($dayItems)) {
                        $cellLines = [];
                        foreach ($dayItems as $dItem) {
                            $title = $dItem->display_title;
                            $sTime = Carbon::parse($dItem->start_time)->format('H:i');
                            $eTime = $dItem->end_time ? Carbon::parse($dItem->end_time)->format('H:i') : '00:00';
                            $cellLines[] = "{$title}\n{$sTime} - {$eTime}";
                        }
                        $cellText = implode("\n---\n", $cellLines);
                        $sheet1->setCellValue($colLetter . $rowIndex, $cellText);
                        $sheet1->getStyle($colLetter . $rowIndex)->getAlignment()->setWrapText(true);
                        $sheet1->getStyle($colLetter . $rowIndex)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    }
                }

                $sheet1->getRowDimension($rowIndex)->setRowHeight(36);
                $rowIndex++;
            }
        }

        // Sheet 1 Formatting
        $lastRowSheet1 = max(4, $rowIndex - 1);
        $sheet1->getStyle('A3:H' . $lastRowSheet1)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet1->getColumnDimension('A')->setWidth(12);
        for ($i = 2; $i <= 8; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet1->getColumnDimension($colLetter)->setWidth(24);
        }
        $sheet1->freezePane('B4');

        // 2. Sheet 2: Detaylı Akış (List View / Import Compatible)
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detaylı Akış');

        $headersSheet2 = [
            'Gün',
            'Başlangıç',
            'Bitiş',
            'Program',
            'Özel Başlık',
            'Yayın Türü',
            'Canlı',
            'Tekrar',
            'Paket',
            'Akışta Görünür',
        ];

        foreach ($headersSheet2 as $colIdx => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet2->setCellValue($colLetter . '1', $header);
        }

        $sheet2->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet2->getRowDimension(1)->setRowHeight(26);

        $rowIdx2 = 2;
        foreach ($items as $item) {
            $dayName = Schedule::DAYS[$item->day_of_week] ?? 'Pazartesi';
            $startTime = Carbon::parse($item->start_time)->format('H:i');
            $endTime = $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '00:00';
            $programName = $item->program?->name ?? '';
            $customTitle = $item->custom_title ?? '';

            $typeLabel = match (true) {
                (bool) $item->is_live => 'CANLI',
                (bool) $item->is_repeat => 'TEKRAR',
                (bool) ($item->is_package ?? false) => 'PAKET',
                default => 'NORMAL',
            };

            $sheet2->setCellValue('A' . $rowIdx2, $dayName);
            $sheet2->setCellValue('B' . $rowIdx2, $startTime);
            $sheet2->setCellValue('C' . $rowIdx2, $endTime);
            $sheet2->setCellValue('D' . $rowIdx2, $programName);
            $sheet2->setCellValue('E' . $rowIdx2, $customTitle);
            $sheet2->setCellValue('F' . $rowIdx2, $typeLabel);
            $sheet2->setCellValue('G' . $rowIdx2, $item->is_live ? 'Evet' : 'Hayır');
            $sheet2->setCellValue('H' . $rowIdx2, $item->is_repeat ? 'Evet' : 'Hayır');
            $sheet2->setCellValue('I' . $rowIdx2, (! empty($item->is_package)) ? 'Evet' : 'Hayır');
            $sheet2->setCellValue('J' . $rowIdx2, $item->is_active ? 'Evet' : 'Hayır');

            // Alignment formatting
            $sheet2->getStyle('A' . $rowIdx2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle('B' . $rowIdx2 . ':C' . $rowIdx2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle('D' . $rowIdx2 . ':E' . $rowIdx2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet2->getStyle('F' . $rowIdx2 . ':J' . $rowIdx2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet2->getRowDimension($rowIdx2)->setRowHeight(20);
            $rowIdx2++;
        }

        $lastRowSheet2 = max(1, $rowIdx2 - 1);
        $sheet2->getStyle('A1:J' . $lastRowSheet2)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        if ($lastRowSheet2 >= 1) {
            $sheet2->setAutoFilter('A1:J' . $lastRowSheet2);
        }

        for ($i = 1; $i <= 10; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet2->getColumnDimension($colLetter)->setAutoSize(true);
        }
        $sheet2->freezePane('A2');

        // Set active sheet back to Sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
