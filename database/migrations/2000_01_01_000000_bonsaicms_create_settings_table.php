<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema()->create($this->table(), function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema()->dropIfExists($this->table());
    }

    /**
     * The settings driver may live on its own connection, so the table has to
     * be created there and not on the application's default connection.
     */
    protected function schema(): Builder
    {
        return Schema::connection($this->driver()['connection'] ?? null);
    }

    protected function table(): string
    {
        return $this->driver()['table'] ?? 'bonsaicms_settings';
    }

    /**
     * The database driver this migration belongs to, named by
     * "settings.migrations.driver".
     *
     * @return array<string, mixed>
     */
    protected function driver(): array
    {
        $name = config('settings.migrations.driver', 'database');

        return config("settings.drivers.{$name}") ?? [];
    }
};
