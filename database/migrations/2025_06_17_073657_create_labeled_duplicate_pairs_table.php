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
        Schema::create('labeled_duplicate_pairs', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->uuid('question1_id');
            $table->uuid('question2_id');
            $table->boolean('is_duplicate');
            $table->string('group_id');
            $table->string('source')->default('manual'); 
            $table->timestamps();

            $table->foreign('question1_id')->references('id')->on('questions')->onDelete('cascade');
            $table->foreign('question2_id')->references('id')->on('questions')->onDelete('cascade');
            $table->unique(['question1_id', 'question2_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labeled_duplicate_pairs');
    }
};
