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
        if (Schema::hasTable('schedule_template_items') && ! Schema::hasColumn('schedule_template_items', 'is_package')) {
            Schema::table('schedule_template_items', function (Blueprint $table) {
                $table->boolean('is_package')->default(false)->after('is_repeat');
            });
        }

        if (Schema::hasTable('schedule_exception_items') && ! Schema::hasColumn('schedule_exception_items', 'is_package')) {
            Schema::table('schedule_exception_items', function (Blueprint $table) {
                $table->boolean('is_package')->default(false)->after('is_repeat');
            });
        }

        if (Schema::hasTable('schedules') && ! Schema::hasColumn('schedules', 'is_package')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->boolean('is_package')->default(false)->after('is_repeat');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('schedule_template_items') && Schema::hasColumn('schedule_template_items', 'is_package')) {
            Schema::table('schedule_template_items', function (Blueprint $table) {
                $table->dropColumn('is_package');
            });
        }

        if (Schema::hasTable('schedule_exception_items') && Schema::hasColumn('schedule_exception_items', 'is_package')) {
            Schema::table('schedule_exception_items', function (Blueprint $table) {
                $table->dropColumn('is_package');
            });
        }

        if (Schema::hasTable('schedules') && Schema::hasColumn('schedules', 'is_package')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn('is_package');
            });
        }
    }
};
