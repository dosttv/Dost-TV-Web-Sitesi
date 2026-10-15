<?php

namespace App\Filament\Resources\ScheduleTemplates\Pages;

use App\Filament\Resources\ScheduleTemplates\ScheduleTemplateResource;
use App\Models\ScheduleTemplate;
use App\Services\Schedule\ScheduleExcelImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScheduleExcelImportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = ScheduleTemplateResource::class;

    protected static ?string $title = "Excel'den Yayın Dönemi Aktar";

    protected static ?string $slug = 'excel-import';

    protected string $view = 'filament.resources.schedule-templates.pages.schedule-excel-import-page';

    public ?array $data = [];

    public bool $isPreviewLoaded = false;

    public ?string $period_name = null;

    public ?string $valid_from_raw = null;

    public ?string $valid_until_raw = null;

    public ?string $valid_from_formatted = null;

    public ?string $valid_until_formatted = null;

    public int $total_count = 0;

    public int $valid_count = 0;

    public int $error_count = 0;

    public int $warning_count = 0;

    public bool $has_errors = false;

    public bool $has_warnings = false;

    public ?string $general_error = null;

    public array $errorsList = [];

    public array $warningsList = [];

    public array $days_summary = [];

    public array $rows = [];

    public ?string $selectedDay = 'all';

    // Completion state
    public bool $isImported = false;

    public ?int $createdTemplateId = null;

    public ?string $createdTemplateName = null;

    public int $importedItemsCount = 0;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('excel_file')
                    ->label('DOST TV Standart Excel Dosyası (.xlsx, .xls)')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->disk('local')
                    ->directory('temp-schedule-excel')
                    ->maxSize(10240)
                    ->required()
                    ->helperText('Yalnızca DOST TV formatında hazırlanmış Excel dosyalarını yükleyin. Maks 10 MB.'),
            ])
            ->statePath('data');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function fetchPreview(): void
    {
        $formData = $this->form->getState();
        $uploaded = $formData['excel_file'] ?? null;

        if (is_array($uploaded)) {
            $uploaded = reset($uploaded);
        }

        if (blank($uploaded)) {
            Notification::make()
                ->title('Lütfen bir Excel dosyası seçin.')
                ->danger()
                ->send();

            return;
        }

        if ($uploaded instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $fullPath = $uploaded->getRealPath();
        } elseif (is_string($uploaded)) {
            $fullPath = Storage::disk('local')->path($uploaded);
            if (! file_exists($fullPath) && file_exists($uploaded)) {
                $fullPath = $uploaded;
            }
        } else {
            $fullPath = null;
        }

        if (! $fullPath || ! file_exists($fullPath)) {
            Notification::make()
                ->title('Yüklenen dosya sunucuda bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        $service = app(ScheduleExcelImportService::class);

        try {
            $result = $service->parseAndValidate($fullPath);
        } catch (\Throwable $e) {
            Log::error('Schedule Excel Preview Error: ' . $e->getMessage(), ['exception' => $e]);
            Notification::make()
                ->title('Excel dosyası analiz edilirken hata oluştu: ' . $e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->has_errors = $result['has_errors'] ?? false;
        $this->has_warnings = $result['has_warnings'] ?? false;
        $this->total_count = $result['total_count'] ?? 0;
        $this->valid_count = $result['valid_count'] ?? 0;
        $this->error_count = $result['error_count'] ?? 0;
        $this->warning_count = $result['warning_count'] ?? 0;
        $this->period_name = $result['period_name'] ?? null;
        $this->valid_from_raw = $result['valid_from'] ? $result['valid_from']->toDateString() : null;
        $this->valid_until_raw = $result['valid_until'] ? $result['valid_until']->toDateString() : null;
        $this->valid_from_formatted = $result['valid_from_formatted'] ?? null;
        $this->valid_until_formatted = $result['valid_until_formatted'] ?? null;
        $this->general_error = $result['general_error'] ?? null;
        $this->errorsList = $result['errors'] ?? [];
        $this->warningsList = $result['warnings'] ?? [];
        $this->days_summary = $result['days_summary'] ?? [];
        $this->rows = $result['rows'] ?? [];

        $this->isPreviewLoaded = true;
        $this->isImported = false;

        if ($this->has_errors) {
            Notification::make()
                ->title("Excel dosyasında {$this->error_count} adet hata tespit edildi.")
                ->body('Hataları aşağıdaki listeden inceleyip Excel dosyanızı düzelterek tekrar yükleyiniz.')
                ->danger()
                ->send();
        } elseif ($this->warning_count > 0) {
            Notification::make()
                ->title("Kontrol tamamlandı: {$this->total_count} yayın satırı doğrulandı ({$this->warning_count} uyarı mevcut).")
                ->body('Parantezli program açıklamaları normalize edilerek eşleştirildi. Yayın dönemini oluşturabilirsiniz.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title("Kontrol tamamlandı: {$this->total_count} yayın satırı başarıyla doğrulandı.")
                ->success()
                ->send();
        }
    }

    public function createSchedulePeriod(): void
    {
        if (! $this->isPreviewLoaded || empty($this->rows) || $this->has_errors) {
            Notification::make()
                ->title('Hatalı veya kontrol edilmemiş Excel dosyası içe aktarılamaz.')
                ->danger()
                ->send();

            return;
        }

        if (blank($this->period_name)) {
            Notification::make()
                ->title('Akış Adı (Yayın Dönemi Adı) zorunludur.')
                ->danger()
                ->send();

            return;
        }

        if (ScheduleTemplate::where('name', $this->period_name)->exists()) {
            Notification::make()
                ->title('Bu isimde bir yayın dönemi zaten mevcut.')
                ->danger()
                ->send();

            return;
        }

        $service = app(ScheduleExcelImportService::class);

        try {
            DB::transaction(function () use ($service) {
                $template = ScheduleTemplate::create([
                    'name' => $this->period_name,
                    'valid_from' => $this->valid_from_raw,
                    'valid_until' => $this->valid_until_raw,
                    'status' => 'draft',
                    'is_active' => false,
                    'version' => 1,
                ]);

                $createdCount = $service->importToTemplate($template, $this->rows);

                $this->createdTemplateId = $template->id;
                $this->createdTemplateName = $template->name;
                $this->importedItemsCount = $createdCount;
            });
        } catch (\Throwable $e) {
            Log::error('Schedule Period Import Error: ' . $e->getMessage(), ['exception' => $e]);
            Notification::make()
                ->title('Yayın dönemi oluşturulurken veritabanı hatası oluştu: ' . $e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->isImported = true;
        $this->isPreviewLoaded = false;

        \App\Support\SiteCache::forgetHomeHeroPrograms();
        \App\Support\SiteCache::forgetHomepage();

        $userName = auth()->user()?->name ?? 'Kullanıcı';
        \App\Services\Audit\AuditLogger::log(
            action: 'imported',
            message: "{$userName}, haftalık yayın akışını Excel'den aktardı.",
            subject: ScheduleTemplate::find($this->createdTemplateId),
            subjectLabel: $this->createdTemplateName,
            metadata: [
                'template_id' => $this->createdTemplateId,
                'imported_items' => $this->importedItemsCount,
            ]
        );

        Notification::make()
            ->title("Yayın dönemi '{$this->createdTemplateName}' ve {$this->importedItemsCount} yayın satırı başarıyla oluşturuldu!")
            ->success()
            ->send();
    }
}
