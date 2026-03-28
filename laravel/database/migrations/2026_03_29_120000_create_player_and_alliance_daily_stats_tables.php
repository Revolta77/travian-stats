<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('total_population');
            $table->unsignedInteger('village_count');
            $table->integer('population_change')->nullable();
            $table->integer('village_count_change')->nullable();
            $table->unsignedInteger('days_without_change')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'snapshot_date']);
            $table->index('snapshot_date');
        });

        Schema::create('alliance_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alliance_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('total_population');
            $table->unsignedInteger('village_count');
            $table->unsignedInteger('member_count');
            $table->integer('population_change')->nullable();
            $table->integer('village_count_change')->nullable();
            $table->integer('member_count_change')->nullable();
            $table->unsignedInteger('days_without_change')->default(0);
            $table->timestamps();

            $table->unique(['alliance_id', 'snapshot_date']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alliance_daily_stats');
        Schema::dropIfExists('player_daily_stats');
    }
};
