<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema()->create(config('settings.database.table'), function (Blueprint $table) {
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();

            $table->primary('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema()->dropIfExists(config('settings.database.table'));
    }

    /**
     * The settings model may live on its own connection, so the table has to
     * be created there and not on the application's default connection.
     */
    protected function schema()
    {
        return Schema::connection(
            config('settings.database.connection')
        );
    }
};
