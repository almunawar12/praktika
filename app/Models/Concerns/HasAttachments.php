<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Applied to anything that carries documentation (ADD § ADR-06): journals now,
 * monitoring visits next. Gives AttachmentService a real type to work against
 * instead of a bare Model that may or may not have the relation.
 *
 * @phpstan-require-extends Model
 */
trait HasAttachments
{
    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
