<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Models\Schedule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('program_id')
                    ->label('Program')
                    ->relationship('program', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('day_of_week')
                    ->label('Yayın Günü')
                    ->options(Schedule::DAYS)
                    ->required(),

                TimePicker::make('start_time')
                    ->label('Başlangıç Saati')
                    ->seconds(false)
                    ->required(),

                TimePicker::make('end_time')
                    ->label('Bitiş Saati')
                    ->seconds(false)
                    ->rules([
                        fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            if ($value && $get('start_time') && $value <= $get('start_time')) {
                                $fail('Bitiş saati, başlangıç saatinden sonra olmalıdır.');
                            }
                        },
                    ]),

                TextInput::make('custom_title')
                    ->label('Özel Yayın Başlığı (İsteğe Bağlı)')
                    ->placeholder('Boş bırakılırsa program adı kullanılır'),

                Toggle::make('is_live')
                    ->label('Canlı Yayın')
                    ->default(false),

                Toggle::make('is_repeat')
                    ->label('Tekrar Yayın')
                    ->default(false),

                Toggle::make('is_package')
                    ->label('Paket Yayın')
                    ->default(false),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                TextInput::make('note')
                    ->label('Yönetici Notu')
                    ->placeholder('Sadece admin paneline özel not'),
            ]);
    }
}
