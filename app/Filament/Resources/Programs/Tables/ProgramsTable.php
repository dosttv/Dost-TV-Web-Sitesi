<?php

namespace App\Filament\Resources\Programs\Tables;

use App\Models\Program;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Program Adı')
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction) => static::applyTurkishSort($query, 'name', $direction))
                    ->weight('bold'),

                TextColumn::make('categories.name')
                    ->label('Kategoriler')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->separator(', '),
                TextColumn::make('episodes_count')
                    ->label('Bölüm Sayısı')
                    ->counts('episodes')
                    ->formatStateUsing(fn ($state) => "{$state} Bölüm")
                    ->url(fn (Program $record) => \App\Filament\Resources\Programs\ProgramResource::getUrl('edit', ['record' => $record]))
                    ->sortable(),


                TextColumn::make('show_on_public')
                    ->label('Public')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? '👁 Yayında' : '⊘ Pasif')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->action(function (Program $record) {
                        if ($record->status === 'archived') {
                            Notification::make()
                                ->title("Arşivlenmiş programı yayına almak için lütfen önce 'Yönetim Arşivinden Çıkar' işlemini yapın.")
                                ->warning()
                                ->send();
                            return;
                        }

                        if ($record->status === 'completed') {
                            Notification::make()
                                ->title("Sona ermiş programı yayına almak için durumunu aktif yapmalısınız.")
                                ->warning()
                                ->send();
                            return;
                        }

                        $newPublic = ! $record->show_on_public;
                        $record->update([
                            'show_on_public' => $newPublic,
                            'is_active' => $newPublic && in_array($record->status, ['active', 'season_break'], true),
                        ]);
                        Notification::make()
                            ->title("{$record->name} " . ($newPublic ? 'yayına alındı (Yayında).' : 'pasife alındı (Pasif).'))
                            ->success()
                            ->send();
                    })
                    ->tooltip('Görünürlüğü değiştirmek için tıklayın'),

                ToggleColumn::make('is_featured')
                    ->label('Öne Çıkan')
                    ->tooltip('Öne çıkan programlar listelerindeki görünürlüğünü değiştirmek için tıklayın'),

                TextInputColumn::make('sort_order')
                    ->label('Sıralama')
                    ->rules(['required', 'integer', 'min:0'])
                    ->sortable()
                    ->tooltip('Program gösterim sırası (küçük sayı önce gösterilir)'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Program Durumu')
                    ->options(Program::STATUSES),

                SelectFilter::make('categories')
                    ->label('Kategori')
                    ->relationship('categories', 'name'),

                TernaryFilter::make('show_on_public')
                    ->label('Public Görünürlük'),

                TernaryFilter::make('in_schedule')
                    ->label('Yayın Akışında Olanlar')
                    ->placeholder('Tüm Programlar')
                    ->trueLabel('Yayın Akışında Var')
                    ->falseLabel('Yayın Akışında Yok')
                    ->queries(
                        true: function (Builder $query) {
                            $ids = app(\App\Services\Home\HomepageDataService::class)->resolveActivePeriodWeeklyPrograms()->pluck('id')->all();
                            return empty($ids) ? $query->whereRaw('1 = 0') : $query->whereIn('id', $ids);
                        },
                        false: function (Builder $query) {
                            $ids = app(\App\Services\Home\HomepageDataService::class)->resolveActivePeriodWeeklyPrograms()->pluck('id')->all();
                            return empty($ids) ? $query : $query->whereNotIn('id', $ids);
                        },
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('is_live_scheduled')
                    ->label('Canlı Yayınlananlar')
                    ->placeholder('Tüm Programlar')
                    ->trueLabel('Canlı Programlar')
                    ->falseLabel('Bant Programlar')
                    ->queries(
                        true: function (Builder $query) {
                            $ids = app(\App\Services\Home\HomepageDataService::class)->resolveActivePeriodWeeklyLivePrograms()->pluck('id')->all();
                            return empty($ids) ? $query->whereRaw('1 = 0') : $query->whereIn('id', $ids);
                        },
                        false: function (Builder $query) {
                            $ids = app(\App\Services\Home\HomepageDataService::class)->resolveActivePeriodWeeklyLivePrograms()->pluck('id')->all();
                            return empty($ids) ? $query : $query->whereNotIn('id', $ids);
                        },
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                EditAction::make(),

                Action::make('archive')
                    ->label('Yönetim Arşivine Al')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (Program $record) => $record->status !== 'archived')
                    ->requiresConfirmation()
                    ->action(function (Program $record) {
                        $record->update([
                            'status' => 'archived',
                            'show_on_public' => false,
                            'is_active' => false,
                        ]);

                        $userName = auth()->user()?->name ?? 'Kullanıcı';
                        \App\Services\Audit\AuditLogger::log(
                            action: 'archived',
                            message: "{$userName}, {$record->name} programını arşivledi.",
                            subject: $record,
                            subjectLabel: $record->name,
                        );

                        Notification::make()
                            ->title("{$record->name} yönetim arşivine alındı.")
                            ->warning()
                            ->send();
                    }),

                Action::make('unarchive')
                    ->label('Yönetim Arşivinden Çıkar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (Program $record) => $record->status === 'archived')
                    ->action(function (Program $record) {
                        $record->update([
                            'status' => 'active',
                            'show_on_public' => true,
                            'is_active' => true,
                        ]);

                        $userName = auth()->user()?->name ?? 'Kullanıcı';
                        \App\Services\Audit\AuditLogger::log(
                            action: 'restored',
                            message: "{$userName}, {$record->name} programını arşivden çıkardı.",
                            subject: $record,
                            subjectLabel: $record->name,
                        );

                        Notification::make()
                            ->title("{$record->name} yönetim arşivinden çıkarıldı.")
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn (Program $record) => $record->episodes()->count() === 0 && $record->schedules()->count() === 0)
                    ->before(function (Program $record) {
                        $userName = auth()->user()?->name ?? 'Kullanıcı';
                        \App\Services\Audit\AuditLogger::log(
                            action: 'deleted',
                            message: "{$userName}, {$record->name} programını kalıcı olarak sildi.",
                            subject: $record,
                            subjectLabel: $record->name,
                            isDestructive: true,
                        );
                    }),
            ]);
    }

    public static function applyTurkishSort(Builder $query, string $column = 'name', string $direction = 'asc'): Builder
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        // Push management-archived programs to the bottom; active programs remain at the top
        $query->orderByRaw("CASE WHEN status = 'archived' THEN 1 ELSE 0 END ASC");

        if ($driver === 'mysql') {
            $version = '';
            try {
                $versionResult = $connection->select('SELECT VERSION() as v');
                $version = $versionResult[0]->v ?? '';
            } catch (\Throwable $e) {
                // Fallback if version check fails
            }

            $isMariaDb = str_contains(strtolower($version), 'mariadb');
            $isMysql8 = ! $isMariaDb && version_compare($version, '8.0.0', '>=');

            $collation = $isMysql8 ? 'utf8mb4_tr_0900_ai_ci' : 'utf8mb4_turkish_ci';

            return $query->orderByRaw("{$column} COLLATE {$collation} {$direction}");
        }

        if ($driver === 'sqlite') {
            return $query->orderByRaw("LOWER({$column}) {$direction}");
        }

        return $query->orderBy($column, $direction);
    }
}
