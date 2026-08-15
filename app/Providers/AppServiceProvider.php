<?php

namespace App\Providers;

use App\Support\CompanyContext;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanyContext::class, fn () => new CompanyContext);
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
