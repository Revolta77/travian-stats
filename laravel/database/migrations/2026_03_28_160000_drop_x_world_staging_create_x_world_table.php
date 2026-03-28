<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('x_world_staging');

        if (Schema::hasTable('x_world')) {
            return;
        }

        // Presne 16 stĺpcov v tomto poradí ako v Travian INSERT INTO x_world VALUES (...)
        Schema::create('x_world', function (Blueprint $table) {
            $table->integer('field_id');
            $table->integer('x');
            $table->integer('y');
            $table->integer('tribe');
            $table->unsignedBigInteger('village_external_id');
            $table->text('village_name');
            $table->unsignedBigInteger('player_external_id');
            $table->text('player_name');
            $table->unsignedBigInteger('alliance_external_id');
            $table->string('alliance_tag', 255)->default('');
            $table->unsignedInteger('population')->nullable();
            $table->string('region', 255)->nullable();
            $table->boolean('is_capital')->default(false);
            $table->boolean('is_city')->nullable();
            $table->boolean('has_harbor')->nullable();
            $table->unsignedInteger('victory_points')->nullable();
            $table->primary('village_external_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `x_world` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('x_world');
    }
};
