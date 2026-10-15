<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('priority')->default(1);
            $table->string('status')->default('draft'); // draft, published, archived
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('schedule_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->constrained('schedule_templates')->onDelete('cascade');
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->integer('day_of_week'); // 0 = Pazartesi, 6 = Pazar
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
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_template_items');
        Schema::dropIfExists('schedule_templates');
    }
};
