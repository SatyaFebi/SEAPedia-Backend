<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() === 'sqlite') {
                $pdo = $connection->getPdo();
                if (method_exists($pdo, 'sqliteCreateFunction')) {
                    $pdo->sqliteCreateFunction('gen_random_uuid', function () {
                        return (string) Str::uuid();
                    });
                }
            }
        } catch (\Throwable $e) {
            // Silence connection errors during early bootstrap/installation
        }
    }
}
