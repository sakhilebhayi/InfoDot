<?php

namespace App\Services\Licensing;

use Illuminate\Validation\ValidationException;

/** Thrown when a company has no seat left for one more person. It is a validation error, so the person sees the explanation in the form. */
class SeatLimitReached extends ValidationException {}
