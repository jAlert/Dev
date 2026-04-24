<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->foreign('approver_role_id')
                  ->references('id')
                  ->on(config('permission.table_names.roles', 'roles'))
                  ->nullOnDelete();
            $table->boolean('requires_all_approvers')->default(false);
            $table->boolean('is_final_approval')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stages');
    }
};
