<?php

namespace App\Filament\Resources\ScheduleTemplates\Tables;

use App\Models\ScheduleTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ScheduleTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Dönem Adı')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('valid_from')
                    ->label('Başlangıç Tarihi')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('valid_until')
                    ->label('Bitiş Tarihi')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('display_status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Gösterimde' => 'success',
                        'Hazır' => 'gray',
                        'Taslak' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('items_count')
                    ->label('Yayın Sayısı')
                    ->counts('items')
                    ->alignEnd(),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                SelectFilter::make('status_filter')
                    ->label('Durum')
                    ->options([
                        'active' => 'Gösterimde',
                        'ready' => 'Hazır',
                        'draft' => 'Taslak',
                    ])
                    ->query(function ($query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'active' => $query->where('status', 'published')->where('is_active', true),
                            'ready' => $query->where('status', 'published')->where('is_active', false),
                            'draft' => $query->where('status', 'draft')->where('is_active', false),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                // 1. Akışı Düzenle (Her durumda görünür)
                Action::make('open_schedule')
                    ->label('Akışı Düzenle')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('amber')
                    ->url(fn (ScheduleTemplate $record) => route('filament.admin.pages.schedule-calendar', ['template' => $record->id])),

                // 2. Dönemi Düzenle (Her durumda görünür)
                EditAction::make()
                    ->label('Dönemi Düzenle'),

                // 3. Hazır Yap (Yalnız Taslak kayıtta görünür)
                Action::make('make_ready')
                    ->label('Hazır Yap')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->visible(fn (ScheduleTemplate $record) => $record->status === 'draft')
                    ->action(function (ScheduleTemplate $record) {
                        $record->update([
                            'status' => 'published',
                            'is_active' => false,
                        ]);

                        \App\Support\SiteCache::forgetHomeHeroPrograms();
                        \App\Support\SiteCache::forgetHomepage();

                        Notification::make()
                            ->title('Yayın dönemi hazır duruma getirildi.')
                            ->success()
                            ->send();
                    }),

                // 3. Gösterimde Yap (Yalnız Hazır kayıtta görünür)
                Action::make('set_active')
                    ->label('Gösterimde Yap')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (ScheduleTemplate $record) => $record->status === 'published' && ! $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Yayın Dönemini Gösterime Al')
                    ->modalDescription(function () {
                        $activeExists = ScheduleTemplate::where('is_active', true)->exists();
                        if ($activeExists) {
                            return 'Başka bir yayın dönemi şu anda gösterimde. Devam ederseniz mevcut dönem pasife alınacak ve bu dönem gösterime geçecektir.';
                        }

                        return 'Bu yayın dönemini gösterime almak istediğinize emin misiniz?';
                    })
                    ->action(function (ScheduleTemplate $record) {
                        app(\App\Services\Schedule\ScheduleCalendarService::class)->activateTemplate($record);

                        Notification::make()
                            ->title('Yayın dönemi gösterime alındı.')
                            ->success()
                            ->send();
                    }),

                // 4. Sil (Yalnız Taslak veya Hazır kayıtta görünür, Gösterimde olan gizlidir)
                DeleteAction::make()
                    ->label('Sil')
                    ->modalHeading('Bu yayın dönemini silmek istediğinize emin misiniz?')
                    ->visible(fn (ScheduleTemplate $record) => ! $record->is_active)
                    ->before(function (ScheduleTemplate $record) {
                        $userName = auth()->user()?->name ?? 'Kullanıcı';
                        \App\Services\Audit\AuditLogger::log(
                            action: 'deleted',
                            message: "{$userName}, {$record->name} dönemini sildi.",
                            subject: $record,
                            subjectLabel: $record->name,
                            isDestructive: true,
                        );
                    }),
            ]);
    }
}
