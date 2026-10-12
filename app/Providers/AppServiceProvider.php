<?php

namespace App\Providers;

use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(Login::class, fn (Login $event) => app(ActivityLogger::class)->record(ActivityEvent::Login, 'Signed in', null, [], $event->user));
        Event::listen(Logout::class, fn (Logout $event) => $event->user && app(ActivityLogger::class)->record(ActivityEvent::Logout, 'Signed out', null, [], $event->user));

        DB::prohibitDestructiveCommands($this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }
}
