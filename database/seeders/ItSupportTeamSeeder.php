<?php

namespace Database\Seeders;

use App\Models\ItSupportTeam;
use App\Models\ItTicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ItSupportTeamSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['Suporte N1', 'suporte-n1', ['Hardware', 'Software']],
            ['Infraestrutura e Redes', 'infraestrutura-redes', ['Rede']],
            ['Sistemas e Acessos', 'sistemas-acessos', ['Acesso']],
        ];

        foreach ($definitions as [$name, $slug, $categories]) {
            $team = ItSupportTeam::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => "Equipe {$name}", 'max_active_tickets' => 20, 'active' => true]
            );

            $categoryIds = ItTicketCategory::query()->whereIn('slug', collect($categories)->map(fn ($category) => Str::slug($category)))->pluck('id');
            $team->categories()->sync($categoryIds);
        }

        $technician = User::query()->where('email', 'tecnico@siges.local')->first();
        $team = ItSupportTeam::query()->where('slug', 'suporte-n1')->first();
        if ($technician && $team) {
            $team->technicians()->syncWithoutDetaching([
                $technician->id => ['available' => true, 'specialties' => json_encode(['hardware', 'software', 'incidente'])],
            ]);
        }
    }
}
