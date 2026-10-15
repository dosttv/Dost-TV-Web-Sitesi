<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('exception_date');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('override_type')->default('replace_all'); // replace_all, additional
            $table->string('status')->default('published'); // draft, published
            $table->timestamps();
        });

        Schema::create('schedule_exception_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_exception_id')->constrained('schedule_exceptions')->onDelete('cascade');
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('custom_title')->nullable();
            $table->boolean('is_live')->default(false);
            $table->boolean('is_repeat')->default(false);
            $table->boolean('is_package')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_version_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->constrained('schedule_templates')->onDelete('cascade');
            $table->integer('version_number');
            $table->json('snapshot_data');
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('change_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_version_histories');
        Schema::dropIfExists('schedule_exception_items');
        Schema::dropIfExists('schedule_exceptions');
    }
};
