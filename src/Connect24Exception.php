<?php

declare(strict_types=1);

namespace Connect24;

use RuntimeException;

/**
 * Base class, so `catch (Connect24Exception $e)` catches everything this library throws.
 */
class Connect24Exception extends RuntimeException
{
}
