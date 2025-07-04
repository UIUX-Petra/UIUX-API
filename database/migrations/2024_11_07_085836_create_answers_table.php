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
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('vote')->default(0);
            $table->string('image')->nullable();
            $table->text('answer');
            $table->boolean('verified')->default(0);
            $table->uuid('question_id')->nullable();
            $table->uuid('user_id');
            $table->integer('report')->default(0);
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->onDelete('set null');
            // $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('question_id');
            $table->index('user_id');
            $table->index('report');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
