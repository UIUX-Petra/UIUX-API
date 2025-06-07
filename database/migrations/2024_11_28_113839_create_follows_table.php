<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->uuid('id')->primary();            
            $table->foreignUuid('follower_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignUuid('followed_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['follower_id','followed_id']);
            $table->index('followed_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
