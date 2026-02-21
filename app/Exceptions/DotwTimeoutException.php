<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown by DotwService::post() when the DOTW API does not respond within the configured timeout window.
 *
 * Extends \Exception (NOT \RuntimeException) so that resolvers can distinguish timeout errors
 * from credential errors (which extend RuntimeException) and catch them independently.
 *
 * Catch order in resolvers must be:
 *   DotwTimeoutException → RuntimeException → \Exception
 *
 * The class identity is the discriminator — no custom methods are required.
 */
class DotwTimeoutException extends \Exception {}
