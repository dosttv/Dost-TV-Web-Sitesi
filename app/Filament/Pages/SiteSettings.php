<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\MailSettings;
use App\Support\SiteCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.site-settings';

    protected static ?string $navigationLabel = 'Site Ayarları';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Site Ayarları';

    protected static ?string $slug = 'site-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Site genelindeki sistem, global SEO ve entegrasyon parametrelerini tek merkezden yönetin.';
    }

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('site-settings-tabs')
                    ->tabs([
                        // 1. Genel Sekmesi
                        Tab::make('Genel')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                TextInput::make('title_suffix')
                                    ->label('Başlık Son Eki (Title Suffix)')
                                    ->placeholder('| DOST TV')
                                    ->helperText('Tüm sayfa başlıklarının sonuna otomatik eklenir (Örn: Programlar | DOST TV).')
                                    ->maxLength(100),

                                TextInput::make('system_email')
                                    ->label('Sistem / Bildirim E-postası')
                                    ->email()
                                    ->placeholder('sistem@dosttv.com')
                                    ->helperText('İletişim ve sistem bildirimlerinin yönlendirileceği e-posta adresi.')
                                    ->maxLength(150),

                                FileUpload::make('default_og_image')
                                    ->label('Varsayılan Global Paylaşım Görseli (OpenGraph)')
                                    ->image()
                                    ->disk('public')
                                    ->directory('seo')
                                    ->maxSize(5120)
                                    ->helperText('Özel paylaşım görseli bulunmayan sayfalarda ve ana sayfada sosyal medyada (Facebook, X, WhatsApp) kullanılır. (Önerilen: 1200×630 px)'),
                            ]),

                        // 2. SEO & Arama Motorları Sekmesi
                        Tab::make('SEO & Arama Motorları')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Textarea::make('default_meta_description')
                                    ->label('Varsayılan Meta Açıklaması')
                                    ->placeholder('Dost TV - Uydu üzerinden yayın yapan Türkçe tematik televizyon kanalı...')
                                    ->rows(3)
                                    ->maxLength(350)
                                    ->helperText('Özel meta açıklaması tanımlanmamış sayfalarda ve ana sayfada arama motorları için fallback olarak kullanılır.'),

                                Toggle::make('search_engine_indexing')
                                    ->label('Arama Motorlarında İndeksleme (Robots Meta)')
                                    ->helperText('Açık: index, follow (Google ve diğer arama motorları siteyi dizine ekler). Kapalı: noindex, nofollow (Arama motorlarının siteyi dizine eklemesi engellenir).')
                                    ->default(true),

                                Select::make('canonical_url_mode')
                                    ->label('Canonical URL Davranışı')
                                    ->options([
                                        'current_url' => 'Geçerli Sayfa URL\'i (Önerilen)',
                                        'domain_root' => 'Ana Alan Adı Tabanlı',
                                    ])
                                    ->default('current_url')
                                    ->helperText('Arama motorlarına bildirilecek standart kopya (canonical) bağlantı kuralı.'),
                            ]),

                        // 3. Entegrasyonlar & Doğrulama Sekmesi
                        Tab::make('Entegrasyonlar & Doğrulama')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                TextInput::make('google_analytics_id')
                                    ->label('Google Analytics Ölçüm Kimliği (GA4)')
                                    ->placeholder('G-XXXXXXXXXX')
                                    ->helperText('Google Analytics 4 (gtag.js) ölçüm kimliği.')
                                    ->maxLength(50),

                                TextInput::make('google_tag_manager_id')
                                    ->label('Google Tag Manager ID')
                                    ->placeholder('GTM-XXXXXXX')
                                    ->helperText('Google Tag Manager kapsayıcı kimliği.')
                                    ->maxLength(50),

                                TextInput::make('google_site_verification')
                                    ->label('Google Search Console Doğrulama Kodu')
                                    ->placeholder('Google doğrulama anahtarı veya tam <meta> etiketi')
                                    ->helperText('Google Arama Konsolu mülk doğrulama kodu veya meta etiketi.'),

                                Textarea::make('custom_head_code')
                                    ->label('Head İçi Özel Kodlar (<head>)')
                                    ->placeholder('<!-- Yandex, Bing veya diğer doğrulama meta etiketleri -->')
                                    ->rows(4)
                                    ->helperText('<head> etiketinin kapanışından hemen önce public sayfalara enjekte edilir.'),

                                Textarea::make('custom_body_code')
                                    ->label('Body Sonu Özel Kodlar (</body>)')
                                    ->placeholder('<!-- Özel widget, chat veya takip scriptleri -->')
                                    ->rows(4)
                                    ->helperText('</body> etiketinin kapanışından hemen önce public sayfalara enjekte edilir.'),
                            ]),

                        // 4. E-posta (SMTP) Sekmesi
                        Tab::make('E-posta (SMTP)')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Toggle::make('mail_enabled')
                                    ->label('Panelden Girilen SMTP Ayarlarını Kullan')
                                    ->helperText('Açık: Sitedeki tüm e-postalar (iletişim formu, davetler, şifre sıfırlama) aşağıdaki SMTP bilgileriyle gönderilir. Kapalı: Sunucudaki .env MAIL_* ayarları kullanılır.')
                                    ->default(false)
                                    ->live(),

                                Grid::make(2)->schema([
                                    TextInput::make('mail_host')
                                        ->label('SMTP Sunucusu')
                                        ->placeholder('smtp.gmail.com')
                                        ->required(fn ($get) => (bool) $get('mail_enabled'))
                                        ->maxLength(255),

                                    TextInput::make('mail_port')
                                        ->label('Port')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(65535)
                                        ->placeholder('587')
                                        ->required(fn ($get) => (bool) $get('mail_enabled')),

                                    Select::make('mail_encryption')
                                        ->label('Şifreleme')
                                        ->options(MailSettings::ENCRYPTION_OPTIONS)
                                        ->default('tls')
                                        ->selectablePlaceholder(false),

                                    TextInput::make('mail_username')
                                        ->label('Kullanıcı Adı')
                                        ->placeholder('bildirim@dosttv.com')
                                        ->autocomplete('off')
                                        ->maxLength(255),

                                    TextInput::make('mail_password')
                                        ->label('Şifre')
                                        ->password()
                                        ->revealable()
                                        ->autocomplete('new-password')
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->helperText(fn () => filled(SiteSetting::current()->mail_password)
                                            ? 'Kayıtlı bir şifre var. Değiştirmek istemiyorsanız boş bırakın.'
                                            : 'Şifre şifrelenmiş olarak saklanır. Gmail için "Uygulama Şifresi" kullanın.'),

                                    TextInput::make('mail_from_address')
                                        ->label('Gönderen E-posta Adresi')
                                        ->email()
                                        ->placeholder('info@dosttv.com')
                                        ->required(fn ($get) => (bool) $get('mail_enabled'))
                                        ->maxLength(255),

                                    TextInput::make('mail_from_name')
                                        ->label('Gönderen Adı')
                                        ->placeholder('DOST TV')
                                        ->maxLength(255),
                                ]),

                                Actions::make([
                                    Action::make('sendTestMail')
                                        ->label('Test E-postası Gönder')
                                        ->icon('heroicon-o-paper-airplane')
                                        ->color('gray')
                                        ->modalHeading('Test E-postası Gönder')
                                        ->modalDescription('Formdaki güncel SMTP bilgileriyle (kaydetmeden önce de) bir test e-postası gönderilir.')
                                        ->modalSubmitActionLabel('Gönder')
                                        ->schema([
                                            TextInput::make('recipient')
                                                ->label('Alıcı E-posta Adresi')
                                                ->email()
                                                ->required()
                                                ->default(fn () => auth()->user()?->email),
                                        ])
                                        ->action(fn (array $data) => $this->sendTestMail($data['recipient'])),
                                ])->key('mailTestActions'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function sendTestMail(string $recipient): void
    {
        $settings = $this->data ?? [];

        // Use the stored password when the field was left blank.
        if (blank($settings['mail_password'] ?? null)) {
            $settings['mail_password'] = SiteSetting::current()->mail_password;
        }

        if (! MailSettings::isUsable($settings)) {
            Notification::make()
                ->title('SMTP bilgileri eksik')
                ->body('Test için en az SMTP sunucusu, port ve gönderen e-posta adresi girilmelidir.')
                ->danger()
                ->send();

            return;
        }

        $fromAddress = $settings['mail_from_address'];
        $fromName = $settings['mail_from_name'] ?: config('mail.from.name');

        try {
            Mail::build(MailSettings::smtpConfig($settings))->raw(
                "DOST TV mail sistemi başarıyla çalışıyor.\n\nBu e-posta Site Ayarları > E-posta (SMTP) sekmesinden gönderilen bir test mesajıdır.\n\nSunucu: {$settings['mail_host']}:{$settings['mail_port']}",
                fn ($message) => $message->to($recipient)
                    ->from($fromAddress, $fromName)
                    ->subject('DOST TV Test E-postası')
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Test e-postası gönderilemedi')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Test e-postası gönderildi')
            ->body("{$recipient} adresinin gelen kutusunu (ve spam klasörünü) kontrol edin.")
            ->success()
            ->send();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::current()->update($data);
        SiteCache::forgetSiteSetting();

        Notification::make()
            ->title('Site ayarları başarıyla kaydedildi.')
            ->success()
            ->send();
    }
}
