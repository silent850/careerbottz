<?php

namespace App\Policies;

use App\Models\Cv;
use App\Models\User;

class CvPolicy
{
    /**
     * Determine whether the user can view their CV list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the CV.
     */
    public function view(User $user, Cv $cv): bool
    {
        return $cv->user_id === $user->id;
    }

    /**
     * Determine whether the user can create a CV.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the CV.
     */
    public function update(User $user, Cv $cv): bool
    {
        return $cv->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the CV.
     */
    public function delete(User $user, Cv $cv): bool
    {
        return $cv->user_id === $user->id;
    }
}
