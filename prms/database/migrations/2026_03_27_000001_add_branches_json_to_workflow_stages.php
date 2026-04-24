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
            $table->json('branches_json')->nullable()->after('branch_trc_stage_id');
        });

        // Migrate existing fixed-column branch data into branches_json
        DB::table('workflow_stages')
            ->where(function ($q) {
                $q->whereNotNull('branch_ad_referendum_stage_id')
                  ->orWhereNotNull('branch_trc_stage_id');
            })
            ->get()
            ->each(function ($stage) {
                $branches = [];
                if ($stage->branch_ad_referendum_stage_id) {
                    $branches[] = ['label' => 'Forward Ad Referendum', 'stage_id' => $stage->branch_ad_referendum_stage_id];
                }
                if ($stage->branch_trc_stage_id) {
                    $branches[] = ['label' => 'Forward For TRC Deliberation', 'stage_id' => $stage->branch_trc_stage_id];
                }
                DB::table('workflow_stages')
                    ->where('id', $stage->id)
                    ->update(['branches_json' => json_encode($branches)]);
            });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('branches_json');
        });
    }
};
