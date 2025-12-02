<?php

namespace App\Dto\Booking;

use Symfony\Component\Validator\Constraints as Assert;

class BookingRequest
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public ?int $serviceId = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public ?int $providerId = null;

    #[Assert\NotBlank]
    #[Assert\DateTime(format: 'Y-m-d\TH:i:sP')]
    public ?string $startDateTime = null;
}

