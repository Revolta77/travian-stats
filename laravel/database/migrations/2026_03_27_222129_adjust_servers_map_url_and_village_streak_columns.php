<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servers', 'map_sql_url')) {
            if (! Schema::hasColumn('servers', 'base_url')) {
                Schema::table('servers', function (Blueprint $table) {
                    $table->string('base_url')->nullable()->after('slug');
                });
            }

            foreach (DB::table('servers')->whereNotNull('map_sql_url')->cursor() as $row) {
                $mapUrl = $row->map_sql_url;
                $base = is_string($mapUrl) ? preg_replace('#/map\.sql$#i', '', rtrim($mapUrl)) : null;
                if ($base === '') {
                    $base = null;
                }
                DB::table('servers')->where('id', $row->id)->update(['base_url' => $base ?: $mapUrl]);
            }

            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('map_sql_url');
            });
        }

        if (! Schema::hasColumn('villages', 'days_without_change')) {
            Schema::table('villages', function (Blueprint $table) {
                $table->unsignedInteger('days_without_change')->default(0)->after('victory_points');
            });
        }

        if (Schema::hasColumn('village_daily_stats', 'days_without_change')) {
            Schema::table('village_daily_stats', function (Blueprint $table) {
                $table->dropColumn('days_without_change');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('village_daily_stats', 'days_without_change')) {
            Schema::table('village_daily_stats', function (Blueprint $table) {
                $table->unsignedInteger('days_without_change')->default(0)->after('population_change');
            });
        }

        if (Schema::hasColumn('villages', 'days_without_change')) {
            Schema::table('villages', function (Blueprint $table) {
                $table->dropColumn('days_without_change');
            });
        }

        if (Schema::hasColumn('servers', 'base_url') && ! Schema::hasColumn('servers', 'map_sql_url')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('map_sql_url')->nullable()->after('slug');
            });

            foreach (DB::table('servers')->whereNotNull('base_url')->cursor() as $row) {
                $mapUrl = rtrim($row->base_url, '/').'/map.sql';
                DB::table('servers')->where('id', $row->id)->update(['map_sql_url' => $mapUrl]);
            }

            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('base_url');
            });
        }
    }
};
