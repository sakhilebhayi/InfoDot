<?php

namespace App\Services\Onboarding;

use RuntimeException;

/** An uploaded document that cannot be read for company details. The message is safe to show the person. */
class DocumentRejected extends RuntimeException {}
