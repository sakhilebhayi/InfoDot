<?php

namespace App\Services\Licensing;

use RuntimeException;

/** A licence action that cannot be done, with a sentence that is safe to show the person who asked. */
class LicenceRefused extends RuntimeException {}
