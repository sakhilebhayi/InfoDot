<?php

namespace App\Services\Web;

use RuntimeException;

/** A web address that must not be fetched: wrong scheme, credentials in it, an odd port, or a host that is not on the public internet. */
class UnsafeUrl extends RuntimeException {}
