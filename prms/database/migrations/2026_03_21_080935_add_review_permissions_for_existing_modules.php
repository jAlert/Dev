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
        $slugs = DB::table('modules')->pluck('slug');
        foreach ($slugs as $slug) {
            DB::table('permissions')->insertOrIgnore([
                'name' => "review-{$slug}",
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $slugs = DB::table('modules')->pluck('slug');
        foreach ($slugs as $slug) {
            DB::table('permissions')->where('name', "review-{$slug}")->delete();
        }
    }
};
