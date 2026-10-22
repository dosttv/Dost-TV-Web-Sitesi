<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Program;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'page' => Page::class,
            'program' => Program::class,
            'category' => Category::class,
        ]);

        // SMTP credentials managed from Site Ayarları > E-posta (SMTP) override .env.
        $this->app->afterResolving('mail.manager', function () {
            MailSettings::applyFromDatabase();
        });

        View::composer('layouts.app', function ($view) {
            $view->with([
                'siteSettings' => SiteSetting::current(),
            ]);
        });

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->timestamps = false;
                $event->user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ])->save();
                $event->user->timestamps = true;
            }
        });

        \Filament\Actions\CreateAction::configureUsing(function (\Filament\Actions\CreateAction $action): void {
            $action->createAnother(false);
        });
    }
}
