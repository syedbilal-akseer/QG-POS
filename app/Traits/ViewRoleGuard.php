<?php

namespace App\Traits;

/**
 * Drop-in guard for any controller / Livewire component that should be
 * read-only when the user has only a view-* additional role.
 *
 * The user's primary role may still grant write access; this trait only
 * blocks writes for users whose access is granted SOLELY by view-khi /
 * view-lhr / view-all.
 */
trait ViewRoleGuard
{
    /**
     * Does the current user have access only via a view-* additional role?
     *
     * True when the user has a view role AND their primary role is one that
     * wouldn't normally grant access to this module (i.e. it's not admin,
     * cmd-khi, cmd-lhr, cmd-head, or sales-head). In that case writes are blocked.
     */
    protected function isViewOnlyUser(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if (!method_exists($user, 'hasAnyViewRole') || !$user->hasAnyViewRole()) {
            return false;
        }
        // Users whose PRIMARY role already grants module access keep their
        // normal permissions — view-* is just additive for them.
        $primaryWriteRoles = ['admin', 'cmd-khi', 'cmd-lhr', 'cmd-head'];
        return !in_array($user->role, $primaryWriteRoles, true);
    }

    /**
     * Abort with 403 if the current user is view-only. Call at the top of
     * any controller method or Livewire action that performs a write.
     */
    protected function blockIfViewOnly(): void
    {
        if ($this->isViewOnlyUser()) {
            abort(403, 'Read-only access — this account cannot modify records.');
        }
    }
}
