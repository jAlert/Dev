<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->foreignId('current_stage_id')
                  ->nullable()
                  ->after('status')
                  ->constrained('workflow_stages')
                  ->nullOnDelete();
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->after('current_stage_id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropForeign(['current_stage_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['current_stage_id', 'assigned_to']);
        });
    }
};
