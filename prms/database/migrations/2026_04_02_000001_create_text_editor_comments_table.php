<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_editor_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('record_id');
            $table->string('field_slug');
            $table->uuid('comment_id')->unique();   // matches the mark attribute in TipTap
            $table->unsignedBigInteger('user_id');
            $table->string('quoted_text', 500);     // the highlighted text at time of comment
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['record_id', 'field_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_editor_comments');
    }
};
