<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_serial_numbers', function (Blueprint $table) {
            $table->unsignedTinyInteger('session')->default(1)->after('vehicle_type');
        });

        // Update unique constraint — sekarang per date+vehicle_type+session+terminal
        Schema::table('daily_serial_numbers', function (Blueprint $table) {
            $table->dropUnique(['date', 'vehicle_type']);
            $table->unique(['date', 'vehicle_type', 'session', 'terminal_id']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_serial_numbers', function (Blueprint $table) {
            $table->dropUnique(['date', 'vehicle_type', 'session', 'terminal_id']);
            $table->unique(['date', 'vehicle_type']);
            $table->dropColumn('session');
        });
    }
};
