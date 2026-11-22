<?php

namespace App\Services\It;

use App\Models\Department;
use App\Models\ItSupportTeam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class TechnicianAssignment
{
    public function nextTechnicianId(?array $ticketData = null): ?int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Automatic technician assignment must run inside a database transaction.');
        }

        $itDepartmentId = Department::query()
            ->where('slug', 'gerencia-de-informatica')
            ->lockForUpdate()
            ->value('id');

        return $this->rankedTechniciansForDepartment($itDepartmentId, $ticketData)->first()['id'] ?? null;
    }

    public function rankedTechnicians(?array $ticketData = null): Collection
    {
        $itDepartmentId = Department::query()->where('slug', 'gerencia-de-informatica')->value('id');

        return $this->rankedTechniciansForDepartment($itDepartmentId, $ticketData);
    }

    private function rankedTechniciansForDepartment(?int $itDepartmentId, ?array $ticketData = null): Collection
    {
        if (! $itDepartmentId) {
            return collect();
        }

        $team = $this->resolveTeam($ticketData ?? []);
        $query = User::query()
            ->select('users.id', 'users.name')
            ->where('users.status', 'ativo')
            ->where('users.department_id', $itDepartmentId)
            ->withCount([
                'assignedItTickets as open_tickets_count' => fn ($tickets) => $tickets->whereNotIn('status', ['resolvido', 'encerrado']),
                'assignedItTickets as in_progress_tickets_count' => fn ($tickets) => $tickets->where('status', 'em_atendimento'),
                'assignedItTickets as overdue_tickets_count' => fn ($tickets) => $tickets->whereNotIn('status', ['resolvido', 'encerrado'])->where('sla_due_at', '<', now()),
            ]);

        if ($team) {
            $query->join('it_support_team_user', function ($join) use ($team) {
                $join->on('users.id', '=', 'it_support_team_user.user_id')
                    ->where('it_support_team_user.team_id', $team->id)
                    ->where('it_support_team_user.available', true)
                    ->where(function ($query) {
                        $query->whereNull('it_support_team_user.unavailable_until')
                            ->orWhere('it_support_team_user.unavailable_until', '<=', now());
                    });
            });
        }

        return $query->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'score' => $this->score($user, $ticketData ?? [], $team),
            ])
            ->sortBy('score')
            ->values();
    }

    public function resolveTeam(array $ticketData): ?ItSupportTeam
    {
        if (! empty($ticketData['support_team_id'])) {
            return ItSupportTeam::query()->where('active', true)->find($ticketData['support_team_id']);
        }

        if (! empty($ticketData['category_id'])) {
            return ItSupportTeam::query()
                ->where('active', true)
                ->whereHas('categories', fn ($query) => $query->whereKey($ticketData['category_id']))
                ->first();
        }

        return null;
    }

    private function score(User $user, array $ticketData, ?ItSupportTeam $team): int
    {
        $open = (int) $user->open_tickets_count;
        $inProgress = (int) $user->in_progress_tickets_count;
        $overdue = (int) $user->overdue_tickets_count;
        $priorityWeight = ['urgente' => 1, 'alta' => 2, 'media' => 3, 'baixa' => 4][$ticketData['priority'] ?? 'media'] ?? 3;

        $score = ($open * 10) + ($inProgress * 8) + ($overdue * 20) + $priorityWeight;

        if ($team) {
            $member = $team->technicians()->whereKey($user->id)->first();
            $limit = $member?->pivot?->max_active_tickets ?: $team->max_active_tickets;
            if ($limit && $open >= $limit) {
                $score += 1000;
            }

            $specialties = $member?->pivot?->specialties;
            if (is_string($specialties)) {
                $specialties = json_decode($specialties, true) ?: array_filter(array_map('trim', explode(',', $specialties)));
            }
            $specialties = is_array($specialties) ? array_map('mb_strtolower', $specialties) : [];
            $requested = array_filter([
                mb_strtolower((string) ($ticketData['category'] ?? '')),
                mb_strtolower((string) ($ticketData['type'] ?? '')),
            ]);
            if ($requested && array_intersect($requested, $specialties)) {
                $score -= 30;
            }
        }

        return $score;
    }
}
