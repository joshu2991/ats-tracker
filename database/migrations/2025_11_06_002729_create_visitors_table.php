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
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->enum('page_type', ['home', 'analyze']); // Which page was visited
            $table->string('ip_address', 45)->nullable(); // IPv6 compatible
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Indexes for analytics queries
            $table->index('page_type');
            $table->index('created_at');
            $table->index(['page_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
