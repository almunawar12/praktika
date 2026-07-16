<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

/**
 * Attachments have no visibility of their own: a file is readable exactly when its
 * parent is. Restating the rules here would guarantee they drift apart.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('view', $attachment->attachable);
    }

    public function create(User $user): bool
    {
        return $user->can('attachments.create');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if (! $user->can('attachments.delete')) {
            return false;
        }

        // The uploader may remove their own file while the parent still accepts
        // changes; anyone who may edit the parent may also tidy its documentation.
        return $attachment->uploaded_by === $user->id
            || $user->can('update', $attachment->attachable);
    }
}
