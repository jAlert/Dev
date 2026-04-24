<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->json('stage_fields_json')->nullable()->after('has_return_button');
        });

        // Migrate existing reviewer_upload_field → stage_fields_json
        DB::table('workflow_stages')
            ->whereNotNull('reviewer_upload_field')
            ->get()
            ->each(fn($s) => DB::table('workflow_stages')
                ->where('id', $s->id)
                ->update(['stage_fields_json' => json_encode([$s->reviewer_upload_field])])
            );

        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('reviewer_upload_field');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->string('reviewer_upload_field')->nullable();
            $table->dropColumn('stage_fields_json');
        });
    }
};
