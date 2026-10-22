<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the SMTP credentials stored in site_settings (managed from the
 * "E-posta (SMTP)" tab) into Laravel's mail config. When the DB settings are
 * disabled or incomplete, the .env based MAIL_* configuration stays in effect.
 */
class MailSettings
{
    public const ENCRYPTION_OPTIONS = [
        'tls' => 'TLS / STARTTLS (genelde 587)',
        'ssl' => 'SSL (genelde 465)',
        'none' => 'Şifreleme yok (genelde 25)',
    ];

    /**
     * Builds a Laravel SMTP mailer config array from raw setting values.
     */
    public static function smtpConfig(array $settings): array
    {
        $port = (int) ($settings['mail_port'] ?? 587);
        $encryption = $settings['mail_encryption'] ?? 'tls';

        return [
            'transport' => 'smtp',
            'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $settings['mail_host'] ?? null,
            'port' => $port,
            'encryption' => $encryption === 'none' ? null : $encryption,
            'username' => $settings['mail_username'] ?? null,
            'password' => $settings['mail_password'] ?? null,
            'auto_tls' => $encryption !== 'none',
            'timeout' => 15,
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ];
    }

    public static function isUsable(array $settings): bool
    {
        return filled($settings['mail_host'] ?? null)
            && filled($settings['mail_port'] ?? null)
            && filled($settings['mail_from_address'] ?? null);
    }

    /**
     * Applies the DB stored SMTP settings to the runtime mail config.
     * Called lazily when the mail manager is first resolved.
     */
    public static function applyFromDatabase(): void
    {
        try {
            $setting = SiteSetting::current();
        } catch (\Throwable $e) {
            // Table missing (fresh install / migrations pending): keep .env config.
            return;
        }

        if (! $setting->mail_enabled) {
            return;
        }

        $settings = [
            'mail_host' => $setting->mail_host,
            'mail_port' => $setting->mail_port,
            'mail_encryption' => $setting->mail_encryption,
            'mail_username' => $setting->mail_username,
            'mail_password' => $setting->mail_password,
            'mail_from_address' => $setting->mail_from_address,
        ];

        if (! static::isUsable($settings)) {
            Log::warning('Mail settings in site_settings are enabled but incomplete; falling back to .env mail config.');

            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => static::smtpConfig($settings),
            'mail.from.address' => $setting->mail_from_address,
            'mail.from.name' => $setting->mail_from_name ?: config('mail.from.name'),
        ]);
    }
}
