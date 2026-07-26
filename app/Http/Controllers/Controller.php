<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller.
 *
 * The Laravel 11+/12 minimal starter ships this empty — but
 * GuestController + ImpactCellController call `$this->authorize(...)` for
 * row-level policy authorization, which only lives on the
 * `AuthorizesRequests` trait. Without it, /guests/{id} and the ImpactCell
 * CRUD routes throw `Call to undefined method ...::authorize()` (HTTP 500).
 *
 * Adding the trait here fixes the 500 in one place and gives any future
 * controller that extends it `$this->authorize(...)` for free.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
