<?php

namespace App\Filament\Resources\Schedules\Tables;

use App\Models\Schedule;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Gün')
                    ->badge()
                    ->formatStateUsing(fn (int $state) => Schedule::DAYS[$state] ?? '')
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('program.name')
                    ->label('Program')
                    ->description(fn (Schedule $record) => $record->custom_title)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Başlangıç')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('end_time')
                    ->label('Bitiş')
                    ->time('H:i')
                    ->sortable(),

                IconColumn::make('is_live')
                    ->label('Canlı')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedSignal)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('danger')
                    ->falseColor('gray'),

                IconColumn::make('is_repeat')
                    ->label('Tekrar')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedArrowPath)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('warning')
                    ->falseColor('gray'),

                IconColumn::make('is_package')
                    ->label('Paket')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedArchiveBox)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('info')
                    ->falseColor('gray'),

                ToggleColumn::make('is_active')
                    ->label('Akışta Görünür'),

                TextColumn::make('note')
                    ->label('Not')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
            ])
            ->defaultSort('start_time')
            ->filters([
                SelectFilter::make('day_of_week')
                    ->label('Gün')
                    ->options(Schedule::DAYS),

                SelectFilter::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name')
                    ->searchable(),

                TernaryFilter::make('is_live')
                    ->label('Canlı Yayın'),

                TernaryFilter::make('is_repeat')
                    ->label('Tekrar Yayın'),

                TernaryFilter::make('is_package')
                    ->label('Paket Yayın'),

                TernaryFilter::make('is_active')
                    ->label('Aktiflik Durumu'),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('copy_to_day')
                    ->label('Kopyala')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('info')
                    ->form([
                        Select::make('target_day')
                            ->label('Kopyalanacak Gün')
                            ->options(Schedule::DAYS)
                            ->required(),
                    ])
                    ->action(function (Schedule $record, array $data) {
                        $newSchedule = $record->replicate();
                        $newSchedule->day_of_week = (int) $data['target_day'];
                        $newSchedule->save();

                        Notification::make()
                            ->title('Yayın akışı ' . (Schedule::DAYS[$data['target_day']] ?? '') . ' gününe kopyalandı.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('make_active')
                        ->label('Toplu Aktif Yap')
                        ->icon(Heroicon::OutlinedCheck)
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),

                    BulkAction::make('make_inactive')
                        ->label('Toplu Pasif Yap')
                        ->icon(Heroicon::OutlinedXMark)
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
