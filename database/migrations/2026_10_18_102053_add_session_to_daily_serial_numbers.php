<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_serial_numbers', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_serial_numbers', 'session')) {
                $table->unsignedTinyInteger('session')->default(1)->after('vehicle_type');
            }
        });

        // Update unique constraint
        Schema::table('daily_serial_numbers', function (Blueprint $table) {
            try {
                $table->dropUnique(['date', 'vehicle_type']);
            } catch (\Exception $e) {
                // Constraint mungkin sudah tidak ada
            }

            try {
                $table->unique(['date', 'vehicle_type', 'session', 'terminal_id']);
            } catch (\Exception $e) {
                // Constraint mungkin sudah ada
            }
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
