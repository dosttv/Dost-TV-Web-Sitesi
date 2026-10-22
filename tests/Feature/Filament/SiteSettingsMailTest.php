<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SiteSettings;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\MailSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsMailTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Süper Yönetici', 'base_role' => 'super_admin', 'description' => 'Tam yetkili', 'is_system' => true]
        );

        $this->superAdmin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        SiteSetting::current();
    }

    private function smtpFormData(array $overrides = []): array
    {
        return array_merge([
            'mail_enabled' => true,
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'bildirim@dosttv.com',
            'mail_password' => 'super-secret',
            'mail_from_address' => 'info@dosttv.com',
            'mail_from_name' => 'DOST TV',
        ], $overrides);
    }

    public function test_mail_fields_are_rendered_in_site_settings_form(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(SiteSettings::class)
            ->assertFormFieldExists('mail_enabled')
            ->assertFormFieldExists('mail_host')
            ->assertFormFieldExists('mail_port')
            ->assertFormFieldExists('mail_encryption')
            ->assertFormFieldExists('mail_username')
            ->assertFormFieldExists('mail_password')
            ->assertFormFieldExists('mail_from_address')
            ->assertFormFieldExists('mail_from_name');
    }

    public function test_mail_settings_are_saved_and_password_is_encrypted(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(SiteSettings::class)
            ->fillForm($this->smtpFormData())
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = SiteSetting::current()->fresh();
        $this->assertTrue($setting->mail_enabled);
        $this->assertSame('smtp.example.com', $setting->mail_host);
        $this->assertSame(587, $setting->mail_port);
        $this->assertSame('super-secret', $setting->mail_password);

        $raw = DB::table('site_settings')->value('mail_password');
        $this->assertNotSame('super-secret', $raw);
        $this->assertArrayNotHasKey('mail_password', $setting->toArray());
    }

    public function test_blank_password_keeps_the_stored_password(): void
    {
        SiteSetting::current()->update($this->smtpFormData());

        Livewire::actingAs($this->superAdmin)
            ->test(SiteSettings::class)
            ->assertFormSet(['mail_password' => null])
            ->fillForm(['mail_host' => 'smtp.yeni.com', 'mail_password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = SiteSetting::current()->fresh();
        $this->assertSame('smtp.yeni.com', $setting->mail_host);
        $this->assertSame('super-secret', $setting->mail_password);
    }

    public function test_required_fields_are_enforced_when_enabled(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(SiteSettings::class)
            ->fillForm(['mail_enabled' => true, 'mail_host' => null, 'mail_port' => null, 'mail_from_address' => null])
            ->call('save')
            ->assertHasFormErrors(['mail_host' => 'required', 'mail_port' => 'required', 'mail_from_address' => 'required']);
    }

    public function test_enabled_db_settings_override_runtime_mail_config(): void
    {
        SiteSetting::current()->update($this->smtpFormData(['mail_encryption' => 'ssl', 'mail_port' => 465]));

        MailSettings::applyFromDatabase();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('super-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('info@dosttv.com', config('mail.from.address'));
        $this->assertSame('DOST TV', config('mail.from.name'));
    }

    public function test_disabled_db_settings_leave_env_mail_config_untouched(): void
    {
        SiteSetting::current()->update($this->smtpFormData(['mail_enabled' => false]));
        $before = config('mail');

        MailSettings::applyFromDatabase();

        $this->assertSame($before, config('mail'));
    }

    public function test_test_mail_reports_missing_smtp_details(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(SiteSettings::class)
            ->fillForm(['mail_host' => null, 'mail_from_address' => null])
            ->callAction(TestAction::make('sendTestMail')->schemaComponent('mailTestActions', schema: 'form'), ['recipient' => 'test@dosttv.com'])
            ->assertNotified('SMTP bilgileri eksik');
    }

    public function test_test_mail_reports_connection_failure(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(SiteSettings::class)
            ->fillForm($this->smtpFormData(['mail_host' => '127.0.0.1', 'mail_port' => 1, 'mail_encryption' => 'none']))
            ->call('sendTestMail', 'test@dosttv.com')
            ->assertNotified('Test e-postası gönderilemedi');
    }
}
