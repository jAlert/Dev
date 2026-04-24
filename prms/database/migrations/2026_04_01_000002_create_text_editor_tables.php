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
        // Text editor documents table - stores Yjs binary CRDT state
        Schema::create('text_editor_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('record_id');
            $table->string('field_slug');
            $table->longText('binary_state')->nullable(); // base64-encoded Yjs Uint8Array
            $table->timestamps();

            // Foreign key
            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();

            // Unique index
            $table->unique(['record_id', 'field_slug']);
        });

        // Text editor histories table - logs individual insert/delete events
        Schema::create('text_editor_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('record_id');
            $table->string('field_slug');
            $table->unsignedBigInteger('user_id');
            $table->enum('action', ['insert', 'delete']);
            $table->text('content'); // the text that was inserted or deleted
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Text editor reviews table - tracks who has marked "Review Done"
        Schema::create('text_editor_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('record_id');
            $table->string('field_slug');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Unique index
            $table->unique(['record_id', 'field_slug', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('text_editor_reviews');
        Schema::dropIfExists('text_editor_histories');
        Schema::dropIfExists('text_editor_documents');
    }
};
