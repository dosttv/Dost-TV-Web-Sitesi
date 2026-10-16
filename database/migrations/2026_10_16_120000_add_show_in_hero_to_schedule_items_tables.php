<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('schedule_template_items') && ! Schema::hasColumn('schedule_template_items', 'show_in_hero')) {
            Schema::table('schedule_template_items', function (Blueprint $table) {
                $table->boolean('show_in_hero')->default(false)->after('is_active');
            });

            // Backfill existing active live items so they stay on Hero seamlessly
            DB::table('schedule_template_items')
                ->where('is_live', true)
                ->where('is_active', true)
                ->update(['show_in_hero' => true]);
        }

        if (Schema::hasTable('schedule_exception_items') && ! Schema::hasColumn('schedule_exception_items', 'show_in_hero')) {
            Schema::table('schedule_exception_items', function (Blueprint $table) {
                $table->boolean('show_in_hero')->default(false)->after('is_active');
            });

            // Backfill existing active live items so they stay on Hero seamlessly
            DB::table('schedule_exception_items')
                ->where('is_live', true)
                ->where('is_active', true)
                ->update(['show_in_hero' => true]);
        }

        if (Schema::hasTable('schedules') && ! Schema::hasColumn('schedules', 'show_in_hero')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->boolean('show_in_hero')->default(false)->after('is_active');
            });

            DB::table('schedules')
                ->where('is_live', true)
                ->where('is_active', true)
                ->update(['show_in_hero' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('schedule_template_items') && Schema::hasColumn('schedule_template_items', 'show_in_hero')) {
            Schema::table('schedule_template_items', function (Blueprint $table) {
                $table->dropColumn('show_in_hero');
            });
        }

        if (Schema::hasTable('schedule_exception_items') && Schema::hasColumn('schedule_exception_items', 'show_in_hero')) {
            Schema::table('schedule_exception_items', function (Blueprint $table) {
                $table->dropColumn('show_in_hero');
            });
        }

        if (Schema::hasTable('schedules') && Schema::hasColumn('schedules', 'show_in_hero')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('show_in_hero');
            });
        }
    }
};
