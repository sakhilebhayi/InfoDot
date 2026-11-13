<?php

namespace App\Services\Seminars;

use RuntimeException;

/** Dot.Finance could not be reached or refused. The message is safe to show: it never holds anything a host or attendee typed. */
class MarketplaceUnavailable extends RuntimeException {}
