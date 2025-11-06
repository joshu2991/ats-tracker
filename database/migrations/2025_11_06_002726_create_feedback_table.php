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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('rating'); // 1-5 stars
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable(); // IPv6 compatible
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Index for filtering/sorting by rating
            $table->index('rating');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
