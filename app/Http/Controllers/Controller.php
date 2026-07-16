<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Laravel 11+ no longer bundles this into the base controller, but CLAUDE.md
    // requires every action to be authorized, so `$this->authorize()` must exist.
    use AuthorizesRequests;
}
