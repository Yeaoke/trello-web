<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notes', function (Blueprint $table): void {
            $table->increments('note_id');
            $table->integer('user_id');
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->string('color', 6)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('created_datetime')->useCurrent();
            $table->timestamp('updated_datetime')->nullable();

            $table->index('user_id', 'idx_notes_user_id');
            $table->index(['user_id', 'is_archived'], 'idx_notes_user_active');

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('notes');
    }
};