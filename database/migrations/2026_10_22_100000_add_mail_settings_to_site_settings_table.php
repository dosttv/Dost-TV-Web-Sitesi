<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'mail_enabled',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('site_settings', 'mail_enabled')) {
            return;
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('mail_enabled')->default(false);
            $table->string('mail_host')->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->string('mail_encryption', 10)->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('site_settings', 'mail_enabled')) {
            return;
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
