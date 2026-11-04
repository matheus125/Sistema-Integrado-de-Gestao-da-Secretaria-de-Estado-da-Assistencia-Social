<?php

namespace Database\Seeders;

use App\Models\ItTicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ItTicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['Hardware', 'memory', 'primary', ['Computador', 'Monitor', 'Impressora', 'Perifericos']],
            ['Software', 'apps', 'secondary', ['Sistema operacional', 'Office', 'Sistema interno', 'Outros sistemas']],
            ['Rede', 'lan', 'info', ['Internet', 'Wi-Fi', 'Cabeamento', 'VPN']],
            ['Acesso', 'lock_open', 'warning', ['Criacao de usuario', 'Redefinicao de senha', 'Permissao', 'Bloqueio/desbloqueio']],
        ];

        foreach ($groups as $groupIndex => [$name, $icon, $color, $children]) {
            $parent = ItTicketCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => "Categoria principal: {$name}",
                    'active' => true,
                    'icon' => $icon,
                    'color' => $color,
                    'sort_order' => ($groupIndex + 1) * 10,
                ]
            );

            foreach ($children as $childIndex => $childName) {
                ItTicketCategory::query()->updateOrCreate(
                    ['slug' => Str::slug("{$name}-{$childName}")],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'active' => true,
                        'icon' => $icon,
                        'color' => $color,
                        'sort_order' => ($childIndex + 1) * 10,
                    ]
                );
            }
        }
    }
}
