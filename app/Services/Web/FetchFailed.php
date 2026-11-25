<?php

namespace App\Services\Web;

use RuntimeException;

/** A fetch of an allowed address did not give a usable page (an error status, the wrong kind of content, too large, too slow, too many redirects). */
class FetchFailed extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
