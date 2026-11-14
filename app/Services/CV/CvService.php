<?php

namespace App\Services\CV;

use App\Models\Conversation;
use App\Models\Cv;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CvService
{
    /**
     * Create a named CV belonging to the supplied user.
     */
    public function create(User $user, string $name): Cv
    {
        return $user->cvs()->create([
            'name' => $name,
        ]);
    }

    /**
     * Select a CV owned by the conversation's user.
     */
    public function select(Conversation $conversation, int $cvId): Cv
    {
        return DB::transaction(function () use ($conversation, $cvId): Cv {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $user = $lockedConversation->user()->firstOrFail();

            $cv = $user->cvs()
                ->whereKey($cvId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedConversation->active_cv_id = $cv->id;
            $lockedConversation->save();

            return $cv;
        }, 3);
    }

    /**
     * Rename a CV owned by the supplied user.
     */
    public function rename(User $user, int $cvId, string $newName): Cv
    {
        $newName = trim($newName);

        if ($newName === '' || mb_strlen($newName) > 255) {
            throw new InvalidArgumentException(
                'CV name must contain between 1 and 255 characters.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $cvId,
            $newName
        ): Cv {
            $cv = $user->cvs()
                ->whereKey($cvId)
                ->lockForUpdate()
                ->firstOrFail();

            $cv->name = $newName;
            $cv->save();

            return $cv;
        }, 3);
    }

    /**
     * Retrieve the user's CV records for viewing.
     */
    public function listForUser(User $user): Collection
    {
        return $user->cvs()
            ->latest('created_at')
            ->get([
                'id',
                'name',
                'status',
                'created_at',
            ]);
    }

    /**
     * Permanently delete a CV owned by the supplied user.
     */
    public function delete(User $user, int $cvId): void
    {
        DB::transaction(function () use ($user, $cvId): void {
            $cv = $user->cvs()
                ->whereKey($cvId)
                ->lockForUpdate()
                ->firstOrFail();

            $cv->delete();
        }, 3);
    }
}
