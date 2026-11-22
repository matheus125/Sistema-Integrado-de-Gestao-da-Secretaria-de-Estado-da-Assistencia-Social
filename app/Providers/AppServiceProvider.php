<?php

namespace App\Providers;

use App\Models\ItTicket;
use App\Policies\ItTicketPolicy;
use App\Services\Auth\TokenSessionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

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
        Gate::policy(ItTicket::class, ItTicketPolicy::class);

        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => app(TokenSessionPolicy::class)
                ->isValid($token, $isValid),
        );
    }
}
