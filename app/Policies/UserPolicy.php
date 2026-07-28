<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Phase 06e+1 — UserPolicy for `/admin/users` CRUD.
 *
 * Phase 02 already established that only `Administrator` may manage
 * users (the Phase 02 follow-up don'ts in HANDOFF.md + the active-role
 * switching design intentionally kept "user provisioning" as a root
 * privilege). Phase 06e+1 codifies that as a policy so the Administrator
 * admin chrome is enforced consistently across store/show/edit/destroy.
 *
 * Self-edit invariants (cannot delete self, cannot demote self out of
 * Administrator, etc.) live in the controller — they're row-specific
 * checks, not blanket role gates, and don't belong in this file.
 *
 * Auto-discovered by Laravel 12 through conventional namespace mapping
 * (App\Models\User → App\Policies\UserPolicy).
 */
class UserPolicy
{
    use HandlesAuthorization;

    /** Administrator may list users in the admin chrome. */
    public function viewAny(User $actor): bool
    {
        return $this->isAdministrator($actor);
    }

    /** Administrator may view a single user record. */
    public function view(User $actor, User $target): bool
    {
        return $this->isAdministrator($actor);
    }

    /** Administrator may create new users. */
    public function create(User $actor): bool
    {
        return $this->isAdministrator($actor);
    }

    /**
     * Administrator may update any user.
     *
     * Self-edit tightening (can't remove your own Administrator Spatie
     * role etc.) is enforced in `Admin\\UserController::update()` to
     * keep the message context tight and avoid firing 403 from the
     * policy when the error is recoverable (form re-render).
     */
    public function update(User $actor, User $target): bool
    {
        return $this->isAdministrator($actor);
    }

    /**
     * Administrator may delete a user. Self-delete is gated separately
     * (403, hard error) in the controller — policy only answers the
     * role question.
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->isAdministrator($actor);
    }

    /** Single source of truth for "this actor is the root admin". */
    private function isAdministrator(User $actor): bool
    {
        return $actor->hasRole('Administrator');
    }
}
