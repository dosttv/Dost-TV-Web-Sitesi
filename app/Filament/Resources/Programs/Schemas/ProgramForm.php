<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Models\Episode;
use App\Models\Program;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('ProgramFormTabs')
                    ->tabs([
                        Tab::make('📌 Genel Bilgiler')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Program Adı')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                                    TextInput::make('slug')
                                        ->label('Bağlantı Adresi (URL)')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                ]),

                                Textarea::make('short_description')
                                    ->label('Yayın Akışı Kısa Tanımı')
                                    ->helperText('Yayın akışında program adının yanında veya altında gösterilen kısa tanıtım metni.')
                                    ->placeholder('Yayın akışında görünecek kısa tanıtım metni...')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Textarea::make('hero_text')
                                    ->label('Ana Sayfa Hero Metni')
                                    ->helperText('Ana sayfadaki büyük program görselinin üzerinde gösterilir. Kısa ve vurucu, tercihen 2–3 satır olmalıdır.')
                                    ->placeholder('Ana sayfa hero alanında görünecek kısa ve vurucu tanıtım metni...')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Grid::make(2)->schema([
                                    Toggle::make('is_featured')
                                        ->label("Hero'da Göster")
                                        ->helperText("Açık olduğunda program, aktif ve public olduğu sürece ana sayfa Hero slider'ında gösterilir.")
                                        ->default(false)
                                        ->live(),

                                    TextInput::make('sort_order')
                                        ->label('Hero Sırası')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->helperText('Daha küçük sayı Hero\'da daha önce gösterilir. (Örn: 0, 1, 2)'),
                                ]),

                                CheckboxList::make('hero_days')
                                    ->label('Hero Günleri')
                                    ->options([
                                        'monday' => 'Pazartesi',
                                        'tuesday' => 'Salı',
                                        'wednesday' => 'Çarşamba',
                                        'thursday' => 'Perşembe',
                                        'friday' => 'Cuma',
                                        'saturday' => 'Cumartesi',
                                        'sunday' => 'Pazar',
                                    ])
                                    ->columns(4)
                                    ->live()
                                    ->helperText("Programın manuel olarak Hero'da görünmesini istediğiniz günleri seçin. Hiçbir gün seçilmezse program manuel Hero'da gösterilmez. Bugünün canlı yayınları bu ayardan bağımsız olarak otomatik gösterilir.")
                                    ->hint(function ($get) {
                                        if ((bool) $get('is_featured') && empty($get('hero_days'))) {
                                            return "Hero'da Göster açık ancak henüz bir gün seçilmedi. Program manuel Hero'da görünmeyecek.";
                                        }

                                        return null;
                                    })
                                    ->hintIcon('heroicon-m-exclamation-triangle')
                                    ->hintColor('warning')
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->label('Program Tanıtım Metni')
                                    ->helperText('Program detay sayfasında gösterilen genel ve detaylı program açıklaması.')
                                    ->placeholder('Program detay sayfasında görünecek detaylı tanıtım açıklaması...')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Grid::make(2)->schema([
                                    TextInput::make('trailer_url')
                                        ->label('Tanıtım Fragmanı (YouTube URL)')
                                        ->placeholder('https://www.youtube.com/watch?v=...')
                                        ->url(),

                                    TextInput::make('youtube_channel_url')
                                        ->label('YouTube Kanal URL')
                                        ->placeholder('https://www.youtube.com/@kanaladi')
                                        ->url()
                                        ->helperText('Programın resmi YouTube kanal bağlantısı (Bilgilendirme amaçlıdır)'),
                                ]),
                            ]),

                        Tab::make('🖼️ Görseller')
                            ->schema([
                                Grid::make(2)->schema([
                                    FileUpload::make('cover_image')
                                        ->label('Program Kapak Görseli (Dikey)')
                                        ->image()
                                        ->disk('public')
                                        ->directory('programs')
                                        ->maxSize(5120)
                                        ->helperText('Önerilen boyut: 1080 × 1350 px. Maksimum 5 MB.'),

                                    FileUpload::make('horizontal_image')
                                        ->label('Ana Sayfa Hero Görseli')
                                        ->image()
                                        ->disk('public')
                                        ->directory('programs')
                                        ->maxSize(5120)
                                        ->helperText('Ana sayfadaki büyük program alanında kullanılır. Önerilen oran: 1920×700 px. Program adı, yayın günü ve saatini görselin içine yazmayın.'),

                                    FileUpload::make('mobile_hero_image')
                                        ->label('Mobil Hero Görseli')
                                        ->image()
                                        ->disk('public')
                                        ->directory('programs')
                                        ->maxSize(5120)
                                        ->helperText('Mobil ana sayfa Hero görünümünde kullanılır (768px altı ekranlar). Tanımlanmazsa Yatay Hero Görseli otomatik olarak kullanılır. Önerilen boyut: 1080×1350 px.'),

                                    FileUpload::make('program_logo')
                                        ->label('Program Logosu')
                                        ->image()
                                        ->disk('public')
                                        ->directory('programs')
                                        ->maxSize(5120)
                                        ->helperText('Önerilen format: Şeffaf PNG veya WEBP. Maksimum 5 MB.'),

                                     FileUpload::make('default_episode_image')
                                        ->label('Yayın Akışı / Varsayılan Bölüm Görseli')
                                        ->image()
                                        ->disk('public')
                                        ->directory('episodes')
                                        ->maxSize(5120)
                                        ->helperText('Yayın Akışı sayfasında kullanılan yatay program görselidir. Önerilen boyut: 1920×1080 px. Bu alan Ana Sayfa Hero Görseli ve Program Kapak Görselinden bağımsızdır.'),
                                ]),
                            ]),

                        Tab::make('🏷️ Kategoriler')
                            ->schema([
                                Select::make('categories')
                                    ->label('Kategoriler')
                                    ->relationship('categories', 'name', fn ($query) => $query->where('slug', '!=', \App\Models\Category::ALL_CATEGORIES_SLUG))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Kategori Adı')
                                            ->required(),
                                    ]),
                            ]),

                        Tab::make('🔍 SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Google Arama Başlığı (Meta Title)')
                                    ->placeholder('Boş bırakılırsa program adı kullanılır'),

                                Textarea::make('meta_description')
                                    ->label('SEO Açıklaması')
                                    ->helperText('Google ve arama motorları için özel açıklama. Boş bırakılırsa Program Tanıtım Metni kullanılır.')
                                    ->rows(3)
                                    ->placeholder('Programın Google arama sonuçlarında görünecek özel SEO özeti'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
