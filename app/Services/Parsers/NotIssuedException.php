<?php

namespace App\Services\Parsers;

/**
 * Raised when a parser positively identifies a supplier email but the booking
 * is not yet a real ticket (e.g. Oman Air itinerary without "Document Number").
 * The caller distinguishes this from genuine parse errors so it can silently
 * drop the message instead of moving the file to files_error/ for review.
 */
class NotIssuedException extends \RuntimeException
{
}
