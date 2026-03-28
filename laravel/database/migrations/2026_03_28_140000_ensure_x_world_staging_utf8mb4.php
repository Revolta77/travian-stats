<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('x_world_staging')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `x_world_staging` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        // Bez návratu — ponecháme utf8mb4
    }
};
