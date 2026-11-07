<?php

namespace Database\Seeders;

use App\Models\Build;
use App\Models\Component;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Illustrative catalog prices and stock; these are not live marketplace quotations.
        $sets = [];
        foreach (['65' => 67, '75' => 82] as $format => $keys) {
            $format = (string) $format;
            $parts = [
                ['case', "KeyCraft {$format} Gasket Case", 1800000, ['mount' => 'gasket']],
                ['pcb', "KeyCraft {$format} Hot-swap PCB", 1400000, ['mount' => 'gasket', 'switch_type' => 'mx', 'layout' => 'ansi', 'key_count' => $keys, 'stabilizers' => ['2u' => 3, '6.25u' => 1]]],
                ['plate', "KeyCraft {$format} Polycarbonate Plate", 450000, ['mount' => 'gasket', 'switch_type' => 'mx']],
                ['stabilizer', "KeyCraft {$format} Stabilizer Kit", 300000, ['stabilizers' => ['2u' => 3, '6.25u' => 1]]],
                ['switch', "KeyCraft {$format} Linear MX Switch", 18000, ['switch_type' => 'mx', 'stem' => 'mx']],
                ['keycap', "KeyCraft {$format} PBT ANSI Keycaps", 900000, ['stem' => 'mx', 'layout' => 'ansi', 'key_count' => $keys]],
            ];
            $sets[$format] = [];
            foreach ($parts as [$type, $name, $price, $specs]) {
                $component = Component::firstOrCreate(['name' => $name], [
                    'type' => $type,
                    'price' => $price,
                    'stock' => $type === 'switch' ? 1000 : 20,
                    'format' => $format,
                    'specs' => $specs,
                ]);
                $sets[$format][$component->id] = ['quantity' => $type === 'switch' ? $keys : 1];
                $component->offers()->firstOrCreate(['marketplace' => 'AliExpress (поиск)'], [
                    'url' => 'https://www.aliexpress.com/w/wholesale-'.rawurlencode('mechanical keyboard '.$type).'.html',
                    'price' => $price,
                    'price_updated_at' => now(),
                ]);
            }
        }

        foreach (['admin' => 'DEMO_ADMIN_PASSWORD', 'buyer' => 'DEMO_BUYER_PASSWORD'] as $role => $variable) {
            $password = env($variable);
            if (! is_string($password) || strlen($password) < 8) {
                continue;
            }
            $user = User::firstOrCreate(['email' => $role.'@keycraft.test'], [
                'name' => $role === 'admin' ? 'Администратор KeyCraft' : 'Демо покупатель',
                'password' => $password,
            ]);
            // Never promote a pre-existing account whose password this seeder does not own.
            if ($user->wasRecentlyCreated) {
                // Role is deliberately excluded from mass assignment in User.
                $user->forceFill(['role' => $role])->save();
            } elseif ($user->role !== $role) {
                throw new \RuntimeException('Учебный email уже принадлежит пользователю с другой ролью: '.$user->email);
            }
            if ($role === 'buyer') {
                foreach ($sets as $format => $items) {
                    $build = Build::firstOrCreate(['user_id' => $user->id, 'name' => "Демо сборка {$format}%"], ['budget' => 10000000]);
                    $build->components()->syncWithoutDetaching($items);
                }
            }
        }
    }
}
