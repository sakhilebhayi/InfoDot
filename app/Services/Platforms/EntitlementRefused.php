<?php

namespace App\Services\Platforms;

use RuntimeException;

/**
 * An entitlement change that the rules do not allow (activating a platform that has a price, changing one that is
 * suspended, choosing a person who is not in the company). The message is written for the person who asked.
 */
class EntitlementRefused extends RuntimeException {}
