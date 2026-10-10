<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tariff;

class TariffSeeder extends Seeder
{
    public function run(): void
    {
        // Pakai superadmin sebagai created_by
        $admin = \App\Models\User::where('username', 'superadmin')->first();

        $tariffs = [
            ['vehicle_type' => 'roda23',    'amount' => 2000],
            ['vehicle_type' => 'roda4',     'amount' => 3000],
            ['vehicle_type' => 'rodaplus4', 'amount' => 4000],
            ['vehicle_type' => 'bermalam',  'amount' => 20000],
        ];

        foreach ($tariffs as $tariff) {
            $exists = Tariff::where('vehicle_type', $tariff['vehicle_type'])->exists();

            if (!$exists) {
                Tariff::create([
                    'vehicle_type'   => $tariff['vehicle_type'],
                    'amount'         => $tariff['amount'],
                    'effective_date' => now()->toDateString(),
                    'created_by'     => $admin->id,
                ]);
            }
        }
    }
}
