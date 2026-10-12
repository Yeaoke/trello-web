<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('boards', function (Blueprint $table): void {
            $table->increments('board_id');
            $table->string('title', 255);
            $table->integer('owner_user_id');
            $table->boolean('is_public')->default(false);
            $table->timestamp('created_datetime')->useCurrent();
            $table->timestamp('updated_datetime')->nullable();

            $table->index('owner_user_id', 'idx_owner_user_id');

            $table->foreign('owner_user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('boards');
    }
};