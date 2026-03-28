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
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alliance_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('field_id');
            $table->smallInteger('x');
            $table->smallInteger('y');
            $table->unsignedTinyInteger('tribe');
            $table->string('name');
            $table->string('region')->nullable();
            $table->boolean('is_capital')->default(false);
            $table->boolean('is_city')->nullable();
            $table->boolean('has_harbor')->nullable();
            $table->unsignedInteger('victory_points')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'external_id']);
            $table->index(['server_id', 'x', 'y']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
