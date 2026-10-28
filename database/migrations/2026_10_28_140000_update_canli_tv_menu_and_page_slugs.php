<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update MenuItem url from /canli-tv to /canli-yayin
        DB::table('menu_items')
            ->where('url', '/canli-tv')
            ->update(['url' => '/canli-yayin']);

        // 2. Safely rename old legacy Elementor dummy page slug to avoid any conflict with /canli-yayin
        DB::table('pages')
            ->where('slug', 'canli-yayin')
            ->update([
                'slug' => 'canli-yayin-eski',
                'status' => 'draft',
                'show_in_menu' => false,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->where('url', '/canli-yayin')
            ->update(['url' => '/canli-tv']);

        DB::table('pages')
            ->where('slug', 'canli-yayin-eski')
            ->update([
                'slug' => 'canli-yayin',
                'status' => 'published',
            ]);
    }
};
