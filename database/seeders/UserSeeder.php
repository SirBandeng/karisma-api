<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Terminal;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin — tidak terikat terminal
        User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name'        => 'Super Administrator',
                'password'    => Hash::make('KarismaSuper@2026'),
                'role'        => 'superadmin',
                'is_active'   => true,
                'terminal_id' => null,
            ]
        );

        // Admin per terminal — credential berbeda-beda
        $admins = [
            [
                'terminal' => 'Terminal Madureso',
                'username' => 'madureso',
                'name'     => 'Admin Terminal Madureso',
                'password' => 'Madureso26!',
            ],
            [
                'terminal' => 'Terminal Drs. Prajitno',
                'username' => 'prajitno',
                'name'     => 'Admin Terminal Drs. Prajitno',
                'password' => 'Prajitno@2026',
            ],
            [
                'terminal' => 'Terminal Kutoarjo',
                'username' => 'kutoarjo',
                'name'     => 'Admin Terminal Kutoarjo',
                'password' => 'Kutoarjo@26',
            ],
        ];

        foreach ($admins as $data) {
            $terminal = Terminal::where('name', $data['terminal'])->first();

            User::firstOrCreate(
                ['username' => $data['username']],
                [
                    'name'        => $data['name'],
                    'password'    => Hash::make($data['password']),
                    'role'        => 'admin',
                    'is_active'   => true,
                    'terminal_id' => $terminal->id,
                ]
            );
        }
    }
}
