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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('body');
            $table->enum('type', ['text', 'attachment'])->default('text');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('conversations', function (Blueprint $table){
            $table->foreignId('last_message_id')->nullable()->constrained('messages')->nullOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::table('conversations', function (Blueprint $table){
            $table->dropConstrainedForeignId('last_message_id');
        });
    }
};
