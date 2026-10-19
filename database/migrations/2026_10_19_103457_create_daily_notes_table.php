<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_notes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('terminal_id')->constrained('terminals');
            $table->text('note');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['date', 'terminal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_notes');
    }
};