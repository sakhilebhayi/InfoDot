<?php

namespace App\Services\Platforms;

use RuntimeException;

/**
 * A signed payment event that cannot be applied: the company, platform, plan or bundle it names is unknown or does
 * not fit. The message is for the log only; the caller is told nothing about which part was wrong.
 */
class PaymentEventRefused extends RuntimeException {}
