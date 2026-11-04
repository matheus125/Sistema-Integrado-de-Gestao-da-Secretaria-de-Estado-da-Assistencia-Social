<?php

namespace App\Policies;

use App\Models\ItTicket;
use App\Models\User;

class ItTicketPolicy
{
    public function view(User $user, ItTicket $ticket): bool
    {
        return $user->hasRole('Administrador Geral')
            || $user->can('informatica.aprovar')
            || $ticket->assigned_to === $user->id
            || $ticket->opened_by === $user->id
            || $ticket->department_id === $user->department_id;
    }

    public function update(User $user, ItTicket $ticket): bool
    {
        return $user->can('informatica.editar') && $this->view($user, $ticket);
    }

    public function cancel(User $user, ItTicket $ticket): bool
    {
        return $this->update($user, $ticket);
    }
}
