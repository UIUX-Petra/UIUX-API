<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blocker_id')->constrained('admins')->onDelete('cascade');
            $table->foreignUuid('unblocker_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->foreignUuid('blocked_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('end_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};