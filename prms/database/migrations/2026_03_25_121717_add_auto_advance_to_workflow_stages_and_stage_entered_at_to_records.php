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
            $table->unsignedSmallInteger('auto_advance_days')->nullable()->after('stage_type');
        });

        Schema::table('records', function (Blueprint $table) {
            $table->timestamp('stage_entered_at')->nullable()->after('current_stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('auto_advance_days');
        });

        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn('stage_entered_at');
        });
    }
};
