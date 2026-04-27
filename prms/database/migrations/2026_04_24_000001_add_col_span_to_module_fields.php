<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_fields', function (Blueprint $table) {
            $table->tinyInteger('col_span')->default(1)->after('show_in_index');
        });
    }

    public function down(): void
    {
        Schema::table('module_fields', function (Blueprint $table) {
            $table->dropColumn('col_span');
        });
    }
};
