<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PersistsTablePaginationInUrl;
use App\Models\Episode;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use App\Models\ScheduleVersionHistory;
use App\Services\Audit\AuditLogger;
use App\Services\Schedule\ScheduleCalendarService;
use App\Services\Schedule\ScheduleExcelExportService;
use App\Support\SiteCache;
use Illuminate\Support\Carbon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ScheduleCalendarPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;
    use PersistsTablePaginationInUrl;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Yayın Yönetimi';

    protected static ?string $navigationLabel = 'Yayın Akışı';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Yayın Akışı';

    protected static ?string $slug = 'schedule-calendar';

    protected string $view = 'filament.pages.schedule-calendar';

    public array $excelPreviewResult = [];

    public function updateExcelPreview($fileState): void
    {
        if (empty($fileState)) {
            $this->excelPreviewResult = [];
            return;
        }

        $filePath = is_array($fileState) ? reset($fileState) : $fileState;

        if ($filePath instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $realPath = $filePath->getRealPath();
        } else {
            $realPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
            if (! file_exists($realPath)) {
                $realPath = storage_path('app/' . ltrim($filePath, '/'));
            }
        }

        if (file_exists($realPath)) {
            $service = app(\App\Services\Schedule\ScheduleExcelImportService::class);
            $this->excelPreviewResult = $service->parseAndValidate($realPath);
        } else {
            $this->excelPreviewResult = [];
        }
    }

    public function getMaxContentWidth(): \Filament\Support\Enums\Width | string | null
    {
        return \Filament\Support\Enums\Width::Full;
    }

    public ?int $selectedTemplateId = null;

    public string $viewMode = 'weekly'; // 'daily', 'weekly', 'monthly'

    public int $selectedDay = 0;

    public string $activeDayTab = '0'; // 0 = Pazartesi, 6 = Pazar

    public bool $showTemplateSelector = false;

    protected $queryString = [
        'selectedTemplateId' => ['except' => null, 'as' => 'template'],
        'selectedDay' => ['except' => 0, 'as' => 'day'],
    ];

    public function mount(): void
    {
        if (request()->has('simulated_date')) {
            try {
                \Illuminate\Support\Carbon::setTestNow(request()->query('simulated_date'));
            } catch (\Throwable $e) {
                // safe fallback
            }
        }

        $todayIsoDay = (int) now()->dayOfWeekIso - 1; // 0 = Pazartesi, 6 = Pazar
        if ($todayIsoDay < 0 || $todayIsoDay > 6) {
            $todayIsoDay = 0;
        }

        if (request()->has('day')) {
            $queryDay = (int) request()->query('day');
            if ($queryDay >= 0 && $queryDay <= 6) {
                $this->selectedDay = $queryDay;
            } else {
                $this->selectedDay = $todayIsoDay;
            }
        } else {
            $this->selectedDay = $todayIsoDay;
        }
        $this->activeDayTab = (string) $this->selectedDay;

        if (request()->has('template')) {
            $queryTemplateId = (int) request()->query('template');
            if (ScheduleTemplate::where('id', $queryTemplateId)->where('status', '!=', 'archived')->exists()) {
                $this->selectedTemplateId = $queryTemplateId;
            }
        }

        if (! $this->selectedTemplateId) {
            $service = app(ScheduleCalendarService::class);
            $template = $service->getActiveOrSelectedTemplate();

            if ($template) {
                $this->selectedTemplateId = $template->id;
            } else {
                $fallback = ScheduleTemplate::where('status', '!=', 'archived')->first();
                if ($fallback) {
                    $this->selectedTemplateId = $fallback->id;
                }
            }
        }
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['daily', 'weekly', 'monthly'])) {
            $this->viewMode = $mode;
        }
    }

    public function toggleTemplateSelector(): void
    {
        $this->showTemplateSelector = ! $this->showTemplateSelector;
    }

    public function getSelectedTemplateProperty(): ?ScheduleTemplate
    {
        return app(ScheduleCalendarService::class)->getActiveOrSelectedTemplate($this->selectedTemplateId);
    }

    public function getTemplatesProperty()
    {
        return ScheduleTemplate::query()
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();
    }

    public function getDayCountsProperty(): array
    {
        $template = $this->selectedTemplate;
        if (! $template) {
            return array_fill(0, 7, 0);
        }

        return app(ScheduleCalendarService::class)->getDayCounts($template);
    }

    public function selectTemplate(int $id): void
    {
        $this->selectedTemplateId = $id;
        $this->resetTable();
    }

    public function toggleShowProgramNames(): void
    {
        $template = $this->selectedTemplate;
        if (! $template) {
            return;
        }

        $template->show_program_names = ! ($template->show_program_names ?? true);
        $template->save();

        Notification::make()
            ->title('Program İsimleri Görünürlüğü Güncellendi')
            ->body($template->show_program_names ? 'Program isimleri public yayın akışında gösterilecek.' : 'Program isimleri public yayın akışında gizlendi.')
            ->success()
            ->send();
    }

    public function updatedSelectedTemplateId($value): void
    {
        $this->selectedTemplateId = (int) $value;
        $this->resetTable();
    }

    public function updatedSelectedDay($value): void
    {
        $this->selectedDay = (int) $value;
        $this->activeDayTab = (string) $value;
        $this->resetTable();
    }

    public function toggleItemActive(int $itemId): void
    {
        $item = ScheduleTemplateItem::find($itemId);
        if (! $item) {
            return;
        }

        $newActiveState = ! $item->is_active;

        if ($newActiveState) {
            $service = app(ScheduleCalendarService::class);
            $hasOverlap = $service->checkOverlap(
                $item->schedule_template_id,
                (int) $item->day_of_week,
                $item->start_time,
                $item->end_time,
                $item->id
            );

            if ($hasOverlap) {
                Notification::make()
                    ->title('Aktifleştirme Engellendi!')
                    ->body('Bu saat aralığında aktif bir yayın zaten bulunuyor.')
                    ->danger()
                    ->send();
                return;
            }
        }

        $item->update(['is_active' => $newActiveState]);
        $this->resetTable();

        $startTimeStr = Carbon::parse($item->start_time)->format('H:i');
        $endTimeStr = $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '23:59';
        $logMessage = sprintf(
            '%s %s %s–%s %s.',
            $item->display_title,
            $item->day_name,
            $startTimeStr,
            $endTimeStr,
            $newActiveState ? 'tekrar akışta görünür yapıldı' : 'akışta gizlendi'
        );

        AuditLogger::log(
            action: 'updated',
            message: $logMessage,
            subject: $item,
            subjectLabel: 'Yayın Akışı'
        );

        SiteCache::forgetHomeHeroPrograms();

        Notification::make()
            ->title('Yayın durumu güncellendi: ' . ($newActiveState ? 'Akışta Görünür' : 'Akışta Gizli'))
            ->success()
            ->send();
    }

    public function selectDay(int $day): void
    {
        $this->selectedDay = $day;
        $this->activeDayTab = (string) $day;
        $this->resetTable();
    }

    public bool $showExceptionItems = true;

    public function toggleExceptionView(): void
    {
        $this->showExceptionItems = ! $this->showExceptionItems;
        $this->resetTable();
    }

    public function selectDayTab(string $day): void
    {
        $this->selectDay((int) $day);
    }

    public function getExceptionForSelectedDayProperty(): ?\App\Models\ScheduleException
    {
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $selectedDate = $startOfWeek->copy()->addDays((int) $this->selectedDay)->toDateString();
        return app(ScheduleCalendarService::class)->getExceptionForDate($selectedDate);
    }

    public function removeSelectedDayException(int $exceptionId): void
    {
        app(ScheduleCalendarService::class)->deleteException($exceptionId);
        $this->resetTable();
        Notification::make()
            ->title('Özel yayın akışı kaldırıldı.')
            ->body('Bu tarih için normal haftalık şablon akışına geri dönüldü.')
            ->success()
            ->send();
    }

    public function getActiveOverlaps(int $templateId, int $dayOfWeek, string $startTime, ?string $endTime, ?int $ignoreItemId = null): \Illuminate\Support\Collection
    {
        $query = ScheduleTemplateItem::query()
            ->where('schedule_template_id', $templateId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->with('program');

        if ($ignoreItemId) {
            $query->where('id', '!=', $ignoreItemId);
        }

        $start = Carbon::parse($startTime)->format('H:i:s');
        $end = ($endTime && $endTime !== '00:00') ? Carbon::parse($endTime)->format('H:i:s') : '23:59:59';

        return $query->where(function ($q) use ($start, $end) {
            $q->where(function ($q2) use ($start) {
                $q2->where('start_time', '<=', $start)
                   ->whereRaw("COALESCE(NULLIF(end_time, '00:00:00'), '23:59:59') > ?", [$start]);
            })->orWhere(function ($q3) use ($end) {
                $q3->where('start_time', '<', $end)
                   ->whereRaw("COALESCE(NULLIF(end_time, '00:00:00'), '23:59:59') >= ?", [$end]);
            })->orWhere(function ($q4) use ($start, $end) {
                $q4->where('start_time', '>=', $start)
                   ->whereRaw("COALESCE(NULLIF(end_time, '00:00:00'), '23:59:59') <= ?", [$end]);
            });
        })->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $dayException = $this->exceptionForSelectedDay;

                if ($dayException && $this->showExceptionItems) {
                    return \App\Models\ScheduleExceptionItem::query()
                        ->where('schedule_exception_id', $dayException->id)
                        ->with('program');
                }

                $templateId = $this->selectedTemplateId ?: 0;

                return ScheduleTemplateItem::query()
                    ->where('schedule_template_id', $templateId)
                    ->where('day_of_week', $this->selectedDay)
                    ->with('program');
            })
            ->columns([
                TextColumn::make('start_time')
                    ->label('Başlangıç')
                    ->state(fn ($record) => $record->start_time ? Carbon::parse($record->start_time)->format('H:i') : '—')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary'),

                TextColumn::make('end_time')
                    ->label('Bitiş')
                    ->state(fn ($record) => $record->end_time ? Carbon::parse($record->end_time)->format('H:i') : '—')
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('display_title')
                    ->label('Program')
                    ->description(fn ($record) => $record->note ?: ($record->custom_title ?: null))
                    ->searchable(query: fn ($query, $search) => $query->whereHas('program', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->weight(FontWeight::Bold),

                TextColumn::make('type_badge')
                    ->label('Yayın Türü')
                    ->badge()
                    ->state(fn ($record) => match (true) {
                        (bool) $record->is_live => 'CANLI YAYIN',
                        (bool) $record->is_repeat => 'TEKRAR YAYIN',
                        (bool) ($record->is_package ?? false) => 'PAKET YAYIN',
                        default => 'NORMAL',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'CANLI YAYIN' => 'danger',
                        'TEKRAR YAYIN' => 'warning',
                        'PAKET YAYIN' => 'info',
                        default => 'gray',
                    }),

                ToggleColumn::make('is_active')
                    ->label('Akışta Görünür')
                    ->beforeStateUpdated(function ($record, bool $state) {
                        if ($state && $record instanceof ScheduleTemplateItem) {
                            $overlaps = $this->getActiveOverlaps(
                                $record->schedule_template_id,
                                (int) $record->day_of_week,
                                $record->start_time,
                                $record->end_time,
                                $record->id
                            );

                            if ($overlaps->isNotEmpty()) {
                                $overlapList = $overlaps->map(fn ($item) => [
                                    'title' => $item->display_title,
                                    'start_time' => Carbon::parse($item->start_time)->format('H:i'),
                                    'end_time' => $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '23:59',
                                    'status' => 'Aktif',
                                ])->all();

                                $this->mountAction('confirm_overlap', [
                                    'action_type' => 'toggle_active',
                                    'submit_label' => 'Yine de Aktif Et',
                                    'record_id' => $record->id,
                                    'overlaps' => $overlapList,
                                ]);

                                return false;
                            }
                        }
                    })
                    ->afterStateUpdated(function ($record, bool $state) {
                        $startTimeStr = Carbon::parse($record->start_time)->format('H:i');
                        $endTimeStr = $record->end_time ? Carbon::parse($record->end_time)->format('H:i') : '23:59';
                        $logMessage = sprintf(
                            '%s %s–%s %s.',
                            $record->display_title,
                            $startTimeStr,
                            $endTimeStr,
                            $state ? 'tekrar akışta görünür yapıldı' : 'akışta gizlendi'
                        );

                        AuditLogger::log(
                            action: 'updated',
                            message: $logMessage,
                            subject: $record,
                            subjectLabel: 'Yayın Akışı'
                        );

                        SiteCache::forgetHomeHeroPrograms();
                        SiteCache::forgetHomepage();
                    }),

                ToggleColumn::make('show_in_hero')
                    ->label("Hero'da Göster")
                    ->afterStateUpdated(function ($record, bool $state) {
                        $startTimeStr = Carbon::parse($record->start_time)->format('H:i');
                        $endTimeStr = $record->end_time ? Carbon::parse($record->end_time)->format('H:i') : '23:59';
                        $logMessage = sprintf(
                            '%s %s–%s %s.',
                            $record->display_title,
                            $startTimeStr,
                            $endTimeStr,
                            $state ? "Hero'da gösterilecek şekilde işaretlendi" : "Hero'dan kaldırıldı"
                        );

                        AuditLogger::log(
                            action: 'updated',
                            message: $logMessage,
                            subject: $record,
                            subjectLabel: 'Yayın Akışı'
                        );

                        SiteCache::forgetHomeHeroPrograms();
                        SiteCache::forgetHomepage();
                    }),
            ])
            ->defaultSort('start_time', 'asc')
            ->recordActions([
                EditAction::make('edit')
                    ->label('Düzenle')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->modalHeading('Yayın Kaydını Düzenle')
                    ->modalSubmitActionLabel('Değişiklikleri Kaydet')
                    ->modalCancelActionLabel('Vazgeç')
                    ->fillForm(fn ($record): array => [
                        'program_id' => $record->program_id,
                        'day_of_week' => (int) ($record->day_of_week ?? $this->selectedDay),
                        'start_time' => $record->start_time ? Carbon::parse($record->start_time)->format('H:i') : null,
                        'end_time' => $record->end_time ? Carbon::parse($record->end_time)->format('H:i') : null,
                        'is_live' => (bool) $record->is_live,
                        'is_repeat' => (bool) $record->is_repeat,
                        'is_package' => (bool) ($record->is_package ?? false),
                        'is_active' => (bool) $record->is_active,
                        'show_in_hero' => (bool) ($record->show_in_hero ?? false),
                        'custom_title' => $record->custom_title,
                        'image' => $record->image ?? null,
                        'description' => $record->description ?? null,
                        'note' => $record->note,
                    ])
                    ->schema(fn (): array => $this->getScheduleItemFormSchema())
                    ->action(function ($record, array $data, ScheduleCalendarService $service) {
                        $isActive = (bool) ($data['is_active'] ?? true);

                        if ($isActive && $record instanceof ScheduleTemplateItem) {
                            $overlaps = $this->getActiveOverlaps(
                                $record->schedule_template_id,
                                (int) ($data['day_of_week'] ?? $this->selectedDay),
                                $data['start_time'],
                                $data['end_time'],
                                $record->id
                            );

                            if ($overlaps->isNotEmpty()) {
                                $overlapList = $overlaps->map(fn ($item) => [
                                    'title' => $item->display_title,
                                    'start_time' => Carbon::parse($item->start_time)->format('H:i'),
                                    'end_time' => $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '23:59',
                                    'status' => 'Aktif',
                                ])->all();

                                $data['record_id'] = $record->id;

                                $this->mountAction('confirm_overlap', [
                                    'action_type' => 'edit',
                                    'submit_label' => 'Yine de Kaydet',
                                    'form_data' => $data,
                                    'overlaps' => $overlapList,
                                ]);

                                return;
                            }
                        }

                        $record->update([
                            'program_id' => $data['program_id'],
                            'day_of_week' => (int) ($data['day_of_week'] ?? $this->selectedDay),
                            'start_time' => $data['start_time'],
                            'end_time' => $data['end_time'],
                            'is_live' => (bool) ($data['is_live'] ?? false),
                            'is_repeat' => (bool) ($data['is_repeat'] ?? false),
                            'is_package' => (bool) ($data['is_package'] ?? false),
                            'is_active' => $isActive,
                            'show_in_hero' => (bool) ($data['show_in_hero'] ?? false),
                            'custom_title' => $data['custom_title'] ?? null,
                            'image' => $data['image'] ?? null,
                            'description' => $data['description'] ?? null,
                            'note' => $data['note'] ?? null,
                        ]);

                        if ($record instanceof ScheduleTemplateItem && $record->template && $record->template->status === 'published' && ! $record->template->is_active) {
                            $record->template->update(['status' => 'draft']);
                        }

                        AuditLogger::log(
                            action: 'updated',
                            message: sprintf('%s %s–%s yayını güncellendi.', $record->display_title, Carbon::parse($record->start_time)->format('H:i'), $record->end_time ? Carbon::parse($record->end_time)->format('H:i') : '23:59'),
                            subject: $record,
                            subjectLabel: 'Yayın Akışı'
                        );
                        SiteCache::forgetHomeHeroPrograms();

                        Notification::make()->title('Yayın kaydı güncellendi.')->success()->send();
                    }),

                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Sil')
                        ->modalHeading('Yayın Kaydını Sil')
                        ->modalDescription('Bu yayın kaydını silmek istediğinizden emin misiniz?')
                        ->action(function ($record) {
                            if ($record instanceof ScheduleTemplateItem) {
                                $template = $record->template;
                                $record->delete();

                                if ($template && $template->status === 'published' && ! $template->is_active) {
                                    $template->update(['status' => 'draft']);
                                }
                            } else {
                                $record->delete();
                            }

                            AuditLogger::log(
                                action: 'deleted',
                                message: sprintf('%s yayını silindi.', $record->display_title),
                                subjectLabel: 'Yayın Akışı'
                            );

                            SiteCache::forgetHomeHeroPrograms();
                            Notification::make()->title('Yayın kaydı silindi.')->success()->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Bu gün için henüz yayın planlanmamış.')
            ->emptyStateDescription('Yukarıdaki "+ Yeni Yayın" butonunu kullanarak yeni bir yayın ekleyebilirsiniz.');
    }

    protected function getHeaderActions(): array
    {
        return [
            // 0. Overlap Warning Confirmation Modal Action
            Action::make('confirm_overlap')
                ->label('Yayın Çakışma Uyarısı')
                ->color('warning')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->modalHeading(fn (array $arguments = []): string => match ($arguments['action_type'] ?? '') {
                    'toggle_active' => 'Aktif Yayın Çakışması Uyarısı',
                    default => 'Yayın Saat Çakışması Uyarısı',
                })
                ->modalDescription(fn (array $arguments = []): string => match ($arguments['action_type'] ?? '') {
                    'toggle_active' => 'Bu saat aralığında başka bir aktif yayın bulunuyor.',
                    default => 'Bu saat aralığında başka bir yayın bulunmaktadır.',
                })
                ->modalSubmitActionLabel(fn (array $arguments = []): string => $arguments['submit_label'] ?? 'Yine de Kaydet')
                ->modalCancelActionLabel('İptal')
                ->schema(function (array $arguments = []): array {
                    $overlaps = $arguments['overlaps'] ?? [];
                    $rowsHtml = '';
                    foreach ($overlaps as $item) {
                        $rowsHtml .= '<tr style="border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <td style="padding:8px 12px; font-weight:700; color:#ffffff;">' . e($item['title']) . '</td>
                            <td style="padding:8px 12px; font-weight:600; color:#fbbf24;">' . e($item['start_time']) . ' – ' . e($item['end_time']) . '</td>
                            <td style="padding:8px 12px;">
                                <span style="display:inline-block; padding:2px 8px; border-radius:9999px; background:rgba(52,211,153,0.15); color:#34d399; border:1px solid rgba(52,211,153,0.3); font-size:11px; font-weight:700;">' . e($item['status']) . '</span>
                            </td>
                        </tr>';
                    }

                    return [
                        \Filament\Forms\Components\Placeholder::make('overlap_preview')
                            ->label('Çakışan Yayınlar')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div style="overflow-x:auto; background:#111827; border:1px solid #1f2937; border-radius:10px; padding:12px;">
                                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                                        <thead>
                                            <tr style="border-bottom:1px solid #374151; color:#9ca3af; font-size:11px; text-transform:uppercase;">
                                                <th style="padding:6px 12px;">Program</th>
                                                <th style="padding:6px 12px;">Saat</th>
                                                <th style="padding:6px 12px;">Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ' . $rowsHtml . '
                                        </tbody>
                                    </table>
                                </div>
                            ')),
                    ];
                })
                ->action(function (array $arguments) {
                    $actionType = $arguments['action_type'] ?? 'create';
                    $formData = $arguments['form_data'] ?? [];

                    if ($actionType === 'create') {
                        $templateId = $formData['template_id'];
                        $item = ScheduleTemplateItem::create([
                            'schedule_template_id' => $templateId,
                            'program_id' => $formData['program_id'],
                            'day_of_week' => (int) $formData['day_of_week'],
                            'start_time' => $formData['start_time'],
                            'end_time' => $formData['end_time'],
                            'custom_title' => $formData['custom_title'] ?? null,
                            'image' => $formData['image'] ?? null,
                            'description' => $formData['description'] ?? null,
                            'is_live' => (bool) ($formData['is_live'] ?? false),
                            'is_repeat' => (bool) ($formData['is_repeat'] ?? false),
                            'is_package' => (bool) ($formData['is_package'] ?? false),
                            'is_active' => (bool) ($formData['is_active'] ?? true),
                            'show_in_hero' => (bool) ($formData['show_in_hero'] ?? false),
                            'note' => $formData['note'] ?? null,
                        ]);

                        if ($item->template && $item->template->status === 'published' && ! $item->template->is_active) {
                            $item->template->update(['status' => 'draft']);
                        }

                        AuditLogger::log(
                            action: 'created',
                            message: sprintf(
                                '%s %s–%s akışa eklendi (Çakışma uyarısına rağmen yayın eklendi).',
                                $item->display_title,
                                Carbon::parse($item->start_time)->format('H:i'),
                                $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '23:59'
                            ),
                            subject: $item,
                            subjectLabel: 'Yayın Akışı'
                        );

                        Notification::make()->title('Çakışma uyarısına rağmen yayın eklendi.')->warning()->send();
                    } elseif ($actionType === 'edit') {
                        $record = ScheduleTemplateItem::find($formData['record_id'] ?? null);
                        if ($record) {
                            $record->update([
                                'program_id' => $formData['program_id'],
                                'day_of_week' => (int) ($formData['day_of_week'] ?? $this->selectedDay),
                                'start_time' => $formData['start_time'],
                                'end_time' => $formData['end_time'],
                                'is_live' => (bool) ($formData['is_live'] ?? false),
                                'is_repeat' => (bool) ($formData['is_repeat'] ?? false),
                                'is_package' => (bool) ($formData['is_package'] ?? false),
                                'is_active' => (bool) ($formData['is_active'] ?? true),
                                'show_in_hero' => (bool) ($formData['show_in_hero'] ?? false),
                                'custom_title' => $formData['custom_title'] ?? null,
                                'image' => $formData['image'] ?? null,
                                'description' => $formData['description'] ?? null,
                                'note' => $formData['note'] ?? null,
                            ]);

                            if ($record->template && $record->template->status === 'published' && ! $record->template->is_active) {
                                $record->template->update(['status' => 'draft']);
                            }

                            AuditLogger::log(
                                action: 'updated',
                                message: sprintf(
                                    '%s %s–%s yayını güncellendi (Çakışma uyarısına rağmen yayın güncellendi).',
                                    $record->display_title,
                                    Carbon::parse($record->start_time)->format('H:i'),
                                    $record->end_time ? Carbon::parse($record->end_time)->format('H:i') : '23:59'
                                ),
                                subject: $record,
                                subjectLabel: 'Yayın Akışı'
                            );

                            Notification::make()->title('Çakışma uyarısına rağmen yayın güncellendi.')->warning()->send();
                        }
                    } elseif ($actionType === 'toggle_active') {
                        $record = ScheduleTemplateItem::find($arguments['record_id'] ?? null);
                        if ($record) {
                            $record->update(['is_active' => true]);

                            AuditLogger::log(
                                action: 'updated',
                                message: sprintf(
                                    '%s %s–%s yayını (Çakışma uyarısına rağmen yayın aktif yapıldı).',
                                    $record->display_title,
                                    Carbon::parse($record->start_time)->format('H:i'),
                                    $record->end_time ? Carbon::parse($record->end_time)->format('H:i') : '23:59'
                                ),
                                subject: $record,
                                subjectLabel: 'Yayın Akışı'
                            );

                            Notification::make()->title('Çakışma uyarısına rağmen yayın aktif yapıldı.')->warning()->send();
                        }
                    }

                    $this->resetTable();
                    SiteCache::forgetHomeHeroPrograms();
                    SiteCache::forgetMenu('header_primary');
                }),

            // 0.5. Bu Dönemi Gösterime Al Action
            Action::make('activate_current_template')
                ->label('Bu Dönemi Gösterime Al')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => sprintf('"%s" Dönemini Gösterime Al', $this->selectedTemplate?->name ?? 'Yayın Akışı'))
                ->modalDescription(function (): \Illuminate\Support\HtmlString {
                    $template = $this->selectedTemplate;
                    if (! $template) {
                        return new \Illuminate\Support\HtmlString('Lütfen bir dönem seçiniz.');
                    }

                    $warnings = [];

                    if ($template->items()->count() === 0) {
                        $warnings[] = '<div class="p-2.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 font-semibold text-xs mb-2">⚠️ DİKKAT: Bu dönemde henüz hiçbir yayın akışı kaydı (öğe) bulunmamaktadır! Gösterime alırsanız kamuya açık sitede yayın akışı ve Hero canlı programları boş görünecektir.</div>';
                    }

                    $todayStr = now()->toDateString();
                    if ($template->valid_from && $template->valid_from->toDateString() > $todayStr) {
                        $warnings[] = '<div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs mb-2">⚠️ Bu dönemin başlangıç tarihi (' . $template->valid_from->format('d.m.Y') . ') henüz gelmemiştir.</div>';
                    } elseif ($template->valid_until && $template->valid_until->toDateString() < $todayStr) {
                        $warnings[] = '<div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs mb-2">⚠️ Bu dönemin bitiş tarihi (' . $template->valid_until->format('d.m.Y') . ') geçmiştir.</div>';
                    }

                    $activeTemplate = ScheduleTemplate::where('is_active', true)->where('id', '!=', $template->id)->first();
                    if ($activeTemplate) {
                        $warnings[] = '<div class="text-xs text-slate-300">Şu anda gösterimde olan <strong>' . e($activeTemplate->name) . '</strong> dönemi pasife alınacak ve <strong>' . e($template->name) . '</strong> dönemi kamuya açık sitede gösterime geçecektir.</div>';
                    } else {
                        $warnings[] = '<div class="text-xs text-slate-300">Bu yayın dönemini kamuya açık sitede gösterime almak istediğinize emin misiniz?</div>';
                    }

                    return new \Illuminate\Support\HtmlString(implode('', $warnings));
                })
                ->modalSubmitActionLabel('Evet, Gösterime Al')
                ->modalCancelActionLabel('Vazgeç')
                ->action(function (ScheduleCalendarService $service) {
                    $template = $this->selectedTemplate;
                    if (! $template) {
                        Notification::make()->title('Lütfen önce bir dönem seçiniz.')->danger()->send();
                        return;
                    }

                    $service->activateTemplate($template);

                    Notification::make()
                        ->title('Yayın dönemi kamuya açık sitede gösterime alındı.')
                        ->body("\"{$template->name}\" artık yayında ve ana sayfa Hero canlı programları bu akıştan besleniyor.")
                        ->success()
                        ->send();

                    $this->resetTable();
                }),

            // 1. Taslağı Yayınla Action
            Action::make('publish_template')
                ->label('Taslağı Yayınla')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Yayın Akışı Taslağını Canlıya Al')
                ->modalDescription('Tüm değişiklikler kaydedilecek ve yeni versiyon oluşturularak public sitede yayınlanacaktır.')
                ->schema([
                    TextInput::make('change_summary')
                        ->label('Değişiklik Özeti / Yayın Notu')
                        ->placeholder('Örn. Yaz sezonu ikindi kuşağı saatleri güncellendi.')
                        ->default('Yeni sürüm yayınlandı.')
                        ->required(),
                ])
                ->action(function (array $data, ScheduleCalendarService $service) {
                    $template = $this->selectedTemplate;
                    if (! $template) {
                        return;
                    }

                    $service->publishTemplate($template, auth()->id(), $data['change_summary']);

                    Notification::make()
                        ->title('Yayın Akışı v' . $template->version . ' başarıyla canlıya alındı!')
                        ->success()
                        ->send();
                }),

            // 2. + Akış Ekle Header Action
            Action::make('create_template')
                ->label('+ Akış Ekle')
                ->color('primary')
                ->modalHeading('Yeni Yayın Akışı Oluştur')
                ->schema([
                    TextInput::make('name')
                        ->label('Akış Adı')
                        ->placeholder('Örn: Yaz Akışı 2026')
                        ->required(),

                    Grid::make(2)->schema([
                        DatePicker::make('valid_from')
                            ->label('Başlangıç Tarihi')
                            ->native(false),

                        DatePicker::make('valid_until')
                            ->label('Bitiş Tarihi')
                            ->native(false)
                            ->afterOrEqual('valid_from'),
                    ]),

                    Select::make('creation_mode')
                        ->label('Akış Oluşturma Yöntemi')
                        ->options([
                            'empty' => 'Boş akış oluştur',
                            'copy' => 'Mevcut akıştan kopyala',
                            'excel' => 'Excel’den aktar',
                        ])
                        ->default('empty')
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            if ($state !== 'excel') {
                                $this->excelPreviewResult = [];
                            }
                        })
                        ->required(),

                    Select::make('source_template_id')
                        ->label('Kopyalanacak Akış')
                        ->options(fn () => ScheduleTemplate::pluck('name', 'id'))
                        ->default(fn () => $this->selectedTemplateId)
                        ->visible(fn ($get) => $get('creation_mode') === 'copy')
                        ->required(fn ($get) => $get('creation_mode') === 'copy'),

                    FileUpload::make('excel_file')
                        ->label('Excel / CSV Dosyası (.xlsx, .xls, .csv)')
                        ->disk('local')
                        ->directory('livewire-tmp')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'application/csv',
                            'text/plain',
                        ])
                        ->maxSize(10240)
                        ->visible(fn ($get) => $get('creation_mode') === 'excel')
                        ->required(fn ($get) => $get('creation_mode') === 'excel')
                        ->live()
                        ->afterStateUpdated(fn ($state) => $this->updateExcelPreview($state))
                        ->helperText('Yayın akışı dosyasını seçtikten sonra aşağıdaki önizleme tablosundan verileri ve durumları kontrol edebilirsiniz.'),

                    \Filament\Forms\Components\ViewField::make('excel_preview')
                        ->view('filament.pages.schedule-excel-preview')
                        ->viewData(fn () => ['previewData' => $this->excelPreviewResult])
                        ->visible(fn ($get) => $get('creation_mode') === 'excel'),
                ])
                ->action(function (array $data) {
                    $creationMode = $data['creation_mode'] ?? 'empty';
                    $importService = app(\App\Services\Schedule\ScheduleExcelImportService::class);

                    if ($creationMode === 'excel') {
                        $excelFile = $data['excel_file'] ?? null;
                        if (empty($excelFile)) {
                            Notification::make()->title('Lütfen bir Excel dosyası yükleyiniz.')->danger()->send();
                            return;
                        }

                        $filePath = is_array($excelFile) ? reset($excelFile) : $excelFile;
                        if ($filePath instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                            $realPath = $filePath->getRealPath();
                        } else {
                            $realPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
                            if (! file_exists($realPath)) {
                                $realPath = storage_path('app/' . ltrim($filePath, '/'));
                            }
                        }

                        $parsed = $importService->parseAndValidate($realPath);
                        $this->excelPreviewResult = $parsed;

                        if (! empty($parsed['has_errors'])) {
                            $errCount = $parsed['error_count'] ?? count($parsed['errors'] ?? []);
                            $genErr = ! empty($parsed['general_error']) ? ' (' . $parsed['general_error'] . ')' : '';

                            Notification::make()
                                ->title('Excel İçe Aktarma Engellendi')
                                ->body('Excel dosyasında ' . $errCount . ' adet hata bulundu' . $genErr . '. Lütfen hataları düzelttikten sonra tekrar deneyin.')
                                ->danger()
                                ->persistent()
                                ->actions([
                                    Action::make('download_errors')
                                        ->label('Hata Raporunu İndir (.xlsx)')
                                        ->color('danger')
                                        ->openUrlInNewTab()
                                        ->url(route('schedule.excel.errors', ['key' => base64_encode(json_encode($parsed['errors']))])),
                                ])
                                ->send();

                            return;
                        }

                        if (empty($parsed['rows'])) {
                            Notification::make()->title('Excel dosyasında aktarılacak geçerli satır bulunamadı.')->warning()->send();
                            return;
                        }

                        $template = ScheduleTemplate::create([
                            'name' => $data['name'],
                            'slug' => ScheduleTemplate::generateUniqueSlug($data['name']),
                            'valid_from' => $data['valid_from'] ?? null,
                            'valid_until' => $data['valid_until'] ?? null,
                            'status' => 'draft',
                            'version' => 1,
                            'is_active' => false,
                        ]);

                        $importedCount = $importService->importToTemplate($template, $parsed['rows']);

                        $this->selectedTemplateId = $template->id;
                        $this->excelPreviewResult = [];
                        $this->resetTable();

                        Notification::make()
                            ->title('Yayın akışı oluşturuldu. Şimdi bu akışı bir yayın dönemine bağlayabilirsiniz.')
                            ->body("Toplam {$parsed['total_count']} satırdan {$importedCount} yayın akışı öğesi aktarıldı.")
                            ->success()
                            ->send();

                        $this->redirect(route('filament.admin.pages.schedule-calendar', ['template' => $template->id]));

                        return;
                    }

                    $template = ScheduleTemplate::create([
                        'name' => $data['name'],
                        'slug' => ScheduleTemplate::generateUniqueSlug($data['name']),
                        'valid_from' => $data['valid_from'] ?? null,
                        'valid_until' => $data['valid_until'] ?? null,
                        'status' => 'draft',
                        'version' => 1,
                        'is_active' => false,
                    ]);

                    if ($creationMode === 'copy' && ! empty($data['source_template_id'])) {
                        $source = ScheduleTemplate::with('items')->find($data['source_template_id']);
                        if ($source) {
                            foreach ($source->items as $item) {
                                $newItem = $item->replicate(['schedule_template_id']);
                                $newItem->schedule_template_id = $template->id;
                                $newItem->save();
                            }
                        }
                    }

                    $this->selectedTemplateId = $template->id;
                    $this->resetTable();

                    Notification::make()
                        ->title('Yayın akışı oluşturuldu. Şimdi bu akışı bir yayın dönemine bağlayabilirsiniz.')
                        ->success()
                        ->send();

                    $this->redirect(route('filament.admin.pages.schedule-calendar', ['template' => $template->id]));
                }),

            // 3. Yeni Yayın Ekle Header Action
            Action::make('create_item')
                ->label('+ Yeni Yayın')
                ->color('amber')
                ->modalHeading('Yeni Yayın Ekle')
                ->modalSubmitActionLabel('Yayını Ekle')
                ->modalCancelActionLabel('Vazgeç')
                ->schema(fn (): array => $this->getScheduleItemFormSchema((int) $this->activeDayTab))
                ->action(function (array $data, ScheduleCalendarService $service) {
                    $template = $this->selectedTemplate;
                    if (! $template) {
                        Notification::make()->title('Lütfen önce bir şablon seçiniz.')->danger()->send();
                        return;
                    }

                    $isActive = (bool) ($data['is_active'] ?? true);

                    if ($isActive) {
                        $overlaps = $this->getActiveOverlaps($template->id, (int) $data['day_of_week'], $data['start_time'], $data['end_time']);

                        if ($overlaps->isNotEmpty()) {
                            $overlapList = $overlaps->map(fn ($item) => [
                                'title' => $item->display_title,
                                'start_time' => Carbon::parse($item->start_time)->format('H:i'),
                                'end_time' => $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '23:59',
                                'status' => 'Aktif',
                            ])->all();

                            $data['template_id'] = $template->id;

                            $this->mountAction('confirm_overlap', [
                                'action_type' => 'create',
                                'submit_label' => 'Yine de Ekle',
                                'form_data' => $data,
                                'overlaps' => $overlapList,
                            ]);

                            return;
                        }
                    }

                    $item = ScheduleTemplateItem::create([
                        'schedule_template_id' => $template->id,
                        'program_id' => $data['program_id'],
                        'day_of_week' => (int) $data['day_of_week'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'custom_title' => $data['custom_title'] ?? null,
                        'image' => $data['image'] ?? null,
                        'description' => $data['description'] ?? null,
                        'is_live' => (bool) ($data['is_live'] ?? false),
                        'is_repeat' => (bool) ($data['is_repeat'] ?? false),
                        'is_package' => (bool) ($data['is_package'] ?? false),
                        'is_active' => $isActive,
                        'show_in_hero' => (bool) ($data['show_in_hero'] ?? false),
                        'note' => $data['note'] ?? null,
                    ]);

                    if ($template->status === 'published' && ! $template->is_active) {
                        $template->update(['status' => 'draft']);
                    }

                    AuditLogger::log(
                        action: 'created',
                        message: sprintf('%s %s–%s akışa eklendi.', $item->display_title, Carbon::parse($item->start_time)->format('H:i'), $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '23:59'),
                        subject: $item,
                        subjectLabel: 'Yayın Akışı'
                    );
                    SiteCache::forgetHomeHeroPrograms();

                    $this->resetTable();

                    Notification::make()
                        ->title('Yayın kaydı başarıyla eklendi.')
                        ->success()
                        ->send();
                }),

            // 4. Toplu İşlemler Grubu (Üç Nokta Menüsü)
            ActionGroup::make([
                Action::make('export_excel')
                    ->label('Excel\'e Aktar')
                    ->color('emerald')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->modalHeading('Yayın Akışını Excel\'e Aktar')
                    ->modalDescription(fn (): string => sprintf(
                        'Seçili yayın akışı ("%s") iki çalışma sayfası (Haftalık Görünüm & Detaylı Liste) halinde Excel dosyası olarak indirilecektir.',
                        $this->selectedTemplate?->name ?? 'Seçili Akış'
                    ))
                    ->modalSubmitActionLabel('Excel Dosyasını İndir')
                    ->modalCancelActionLabel('Vazgeç')
                    ->schema([
                        Toggle::make('include_passive')
                            ->label('Pasif Yayınları da Dahil Et')
                            ->default(false)
                            ->helperText('Açık tutulursa akışta gizli olan pasif yayınlar da Excel\'e eklenir.'),
                    ])
                    ->action(function (array $data, ScheduleExcelExportService $exportService) {
                        $template = $this->selectedTemplate;
                        if (! $template) {
                            Notification::make()->title('Lütfen önce bir şablon seçiniz.')->danger()->send();
                            return;
                        }

                        $includePassive = (bool) ($data['include_passive'] ?? false);

                        return $exportService->exportTemplateToExcel($template, $includePassive);
                    }),

                Action::make('duplicate_template')
                    ->label('Gelecek Sezona Kopyala')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->schema([
                        TextInput::make('new_name')
                            ->label('Yeni Şablon Adı')
                            ->default(fn () => ($this->selectedTemplate?->name ?? 'Sezon') . ' (Gelecek Yıl)')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $template = $this->selectedTemplate;
                        if (! $template) {
                            return;
                        }

                        $newTemplate = $template->duplicateForNextYear($data['new_name']);
                        $this->selectedTemplateId = $newTemplate->id;

                        Notification::make()
                            ->title('Yayın akışı gelecek sezon için kopyalandı.')
                            ->success()
                            ->send();
                    }),

                Action::make('copy_week')
                    ->label('Haftayı Başka Şablona Kopyala')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->schema([
                        Select::make('target_template_id')
                            ->label('Hedef Şablon')
                            ->options(ScheduleTemplate::where('id', '!=', $this->selectedTemplateId)->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data, ScheduleCalendarService $service) {
                        $template = $this->selectedTemplate;
                        if (! $template) {
                            return;
                        }

                        $service->copyWeek($template, (int) $data['target_template_id']);

                        Notification::make()
                            ->title('Tüm haftalık akış hedef şablona kopyalandı.')
                            ->success()
                            ->send();
                    }),

                Action::make('shift_times')
                    ->label('Toplu Saat Kaydır (+ / -)')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        Select::make('target_day')
                            ->label('Uygulanacak Gün')
                            ->options(['all' => 'Tüm Hafta (7 Gün)'] + Schedule::DAYS)
                            ->default('all')
                            ->required(),

                        Select::make('direction')
                            ->label('Yön')
                            ->options([
                                'forward' => 'İleri Kaydır (+)',
                                'backward' => 'Geri Kaydır (-)',
                            ])
                            ->default('forward')
                            ->required(),

                        TextInput::make('minutes')
                            ->label('Dakika Miktarı')
                            ->numeric()
                            ->default(30)
                            ->required(),
                    ])
                    ->action(function (array $data, ScheduleCalendarService $service) {
                        $template = $this->selectedTemplate;
                        if (! $template) {
                            return;
                        }

                        $minutes = (int) $data['minutes'];
                        if ($data['direction'] === 'backward') {
                            $minutes = -$minutes;
                        }

                        $targetDay = $data['target_day'] === 'all' ? null : (int) $data['target_day'];
                        $service->shiftTimes($template, $minutes, $targetDay);

                        $this->resetTable();

                        Notification::make()
                            ->title('Saatler başarıyla kaydırıldı.')
                            ->info()
                            ->send();
                    }),
            ])
            ->label('İşlemler')
            ->icon(Heroicon::OutlinedEllipsisVertical)
            ->color('gray'),
        ];
    }

    protected function getScheduleItemFormSchema(?int $defaultDay = null): array
    {
        return [
            Section::make('İçerik')
                ->schema([
                    Select::make('program_id')
                        ->label('Program')
                        ->options(Program::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('custom_title')
                        ->label('Özel Yayın Başlığı (İsteğe Bağlı)')
                        ->placeholder('Boş bırakılırsa program adı kullanılır'),
                ]),

            Section::make('Zamanlama')
                ->schema([
                    Select::make('day_of_week')
                        ->label('Yayın Günü')
                        ->options(Schedule::DAYS)
                        ->default($defaultDay ?? 0)
                        ->required(),

                    Grid::make(2)->schema([
                        TimePicker::make('start_time')
                            ->label('Başlangıç Saati')
                            ->seconds(false)
                            ->required(),

                        TimePicker::make('end_time')
                            ->label('Bitiş Saati')
                            ->seconds(false)
                            ->required()
                            ->rules([
                                fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($value && $get('start_time') && $value <= $get('start_time')) {
                                        $fail('Bitiş saati, başlangıç saatinden sonra olmalıdır.');
                                    }
                                },
                            ]),
                    ]),
                ]),

            Section::make('Yayın Bilgisi')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('is_live')
                            ->label('Canlı Yayın')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $set('show_in_hero', true);
                                    $set('is_active', true);
                                }
                            }),
                        Toggle::make('is_repeat')->label('Tekrar Yayın')->default(false),
                        Toggle::make('is_package')
                            ->label('Paket Yayın')
                            ->default(false)
                            ->helperText('Önceden hazırlanmış/banttan yayınlanan paket içerik.'),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('is_active')
                            ->label('Akışta Görünür')
                            ->default(true)
                            ->helperText('Kapattığınızda yayın kaydı silinmez; public yayın akışında ve Hero slider\'ında gösterilmez. Bu saat aralığı yeni aktif yayın için boş kabul edilir.'),
                        Toggle::make('show_in_hero')
                            ->label("Hero'da Göster")
                            ->default(false)
                            ->helperText('Açık olduğunda bugünün ana sayfa Hero slider\'ında gösterilir. (Akışta Görünür açık olmalıdır).'),
                    ]),
                ]),

            Section::make('Görsel ve Açıklama')
                ->schema([
                    FileUpload::make('image')
                        ->label('Yayın Görseli')
                        ->disk('public')
                        ->directory('schedules')
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios(['16:9'])
                        ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])
                        ->maxSize(5120)
                        ->helperText('Önerilen görsel boyutu: 1920 × 1080 piksel (16:9). En fazla 5 MB boyutunda JPG, PNG veya WEBP yükleyin. Görsel eklenmezse program kapağı kullanılacaktır.'),

                    Textarea::make('description')
                        ->label('Yayın Açıklaması')
                        ->maxLength(1000)
                        ->helperText('Bu açıklama, ilgili yayın public sitede gösterildiğinde kullanılabilir.'),
                ]),

            Section::make('Yönetici Notu')
                ->schema([
                    Textarea::make('note')
                        ->label('Yönetici Notu')
                        ->helperText('Bu not yalnızca yönetim panelinde görünür; public sitede yayınlanmaz.'),
                ]),
        ];
    }
}
