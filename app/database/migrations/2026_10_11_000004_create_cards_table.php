<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cards', function (Blueprint $table): void {
            $table->increments('card_id');
            $table->integer('board_id');
            $table->integer('column_id');
            $table->smallInteger('sort_index');
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->string('color', 6)->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('created_datetime')->useCurrent();
            $table->timestamp('updated_datetime')->nullable();

            $table->index('column_id', 'idx_column_id');
            $table->index(['column_id', 'sort_index'], 'idx_column_sort');

            $table->foreign('board_id')
                ->references('board_id')
                ->on('boards')
                ->onDelete('cascade');

            $table->foreign('column_id')
                ->references('column_id')
                ->on('board_columns')
                ->onDelete('cascade');
        });

        DB::statement('create index "idx_active_board_id" on "cards" ("board_id") where "is_archived" = false');
    }

    public function down(): void {
        Schema::dropIfExists('cards');
    }
};