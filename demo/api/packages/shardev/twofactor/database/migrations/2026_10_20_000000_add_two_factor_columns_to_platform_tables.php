<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Second-factor columns belong to every platform user table, not only the
 * `users` table published by the original package migration (admin/employee
 * guards are the ones that require MFA).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'two_factor_secret')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('two_factor_secret')->nullable();
                $blueprint->text('two_factor_recovery_codes')->nullable();
                $blueprint->timestamp('two_factor_confirmed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'two_factor_secret')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn([
                    'two_factor_secret',
                    'two_factor_recovery_codes',
                    'two_factor_confirmed_at',
                ]);
            });
        }
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        $tables = [];

        foreach ((array) config('platforms.platforms', []) as $platform) {
            $table = is_array($platform) ? ($platform['table'] ?? null) : null;

            if (is_string($table) && $table !== '') {
                $tables[] = $table;
            }
        }

        return array_values(array_unique($tables));
    }
};
