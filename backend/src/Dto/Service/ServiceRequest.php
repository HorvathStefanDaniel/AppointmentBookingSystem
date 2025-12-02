<?php

namespace App\Dto\Service;

use Symfony\Component\Validator\Constraints as Assert;

class ServiceRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Assert\DivisibleBy(30)]
    public ?int $durationMinutes = null;
}

