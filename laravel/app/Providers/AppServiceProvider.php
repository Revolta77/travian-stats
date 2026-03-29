<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
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
        // utf8mb4: varchar(255) index môže prekročiť limit InnoDB na zdieľanom hostingu (napr. 767 / 1000 B).
        Schema::defaultStringLength(191);
    }
}
