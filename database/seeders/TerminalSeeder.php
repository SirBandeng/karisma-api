<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Terminal;

class TerminalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $terminals = [
            ['name' => 'Terminal Madureso', 'location' => 'Temanggung'],
            ['name' => 'Terminal Drs. Prajitno', 'location' => 'Muntilan'],
            ['name' => 'Terminal Kutoarjo', 'location' => 'Kutoarjo'],
        ];

        foreach ($terminals as $terminal) {
            Terminal::firstOrCreate(
                ['name' => $terminal['name']],
                $terminal
            );
        }
    }
}
