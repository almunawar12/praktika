<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a master-data row can not be removed because other records still
 * point at it. Renders as a flash message rather than a 500: this is an ordinary
 * outcome an admin should be told about, not a crash.
 */
class ResourceInUseException extends RuntimeException
{
    public function render(Request $request): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
