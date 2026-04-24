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
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_ad_referendum_stage_id')->nullable()->after('auto_advance_days');
            $table->unsignedBigInteger('branch_trc_stage_id')->nullable()->after('branch_ad_referendum_stage_id');

            $table->foreign('branch_ad_referendum_stage_id')
                  ->references('id')->on('workflow_stages')->nullOnDelete();
            $table->foreign('branch_trc_stage_id')
                  ->references('id')->on('workflow_stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropForeign(['branch_ad_referendum_stage_id']);
            $table->dropForeign(['branch_trc_stage_id']);
            $table->dropColumn(['branch_ad_referendum_stage_id', 'branch_trc_stage_id']);
        });
    }
};
