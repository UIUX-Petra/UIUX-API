<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('title');
            $table->text('detail'); 
            $table->string('status', 50)->default('draft'); // draft, published, archived
            $table->boolean('display_on_web')->default(false); // Kontrol tampilan di web
            $table->timestamp('published_at')->nullable(); // Kapan dipublish
            $table->timestamp('notified_at')->nullable(); // Kapan email dikirim
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};