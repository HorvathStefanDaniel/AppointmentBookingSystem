<?php

namespace App\Exception;

class SlotHoldConflictException extends \RuntimeException
{
    public static function alreadyReserved(): self
    {
        return new self('Slot already reserved.');
    }

    public static function alreadyBooked(): self
    {
        return new self('Slot already booked.');
    }
}

