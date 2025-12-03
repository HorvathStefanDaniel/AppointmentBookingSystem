<?php

namespace App\Entity;

use App\Repository\SlotHoldRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SlotHoldRepository::class)]
#[ORM\Table(name: 'slot_hold')]
#[ORM\UniqueConstraint(name: 'uniq_slot_hold_provider_start', columns: ['provider_id', 'start_date_time'])]
#[ORM\Index(name: 'slot_hold_provider_time_idx', columns: ['provider_id', 'start_date_time', 'end_date_time'])]
#[ORM\Index(name: 'slot_hold_expires_idx', columns: ['expires_at'])]
class SlotHold
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Provider $provider = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startDateTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $endDateTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(Provider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(Service $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStartDateTime(): ?\DateTimeImmutable
    {
        return $this->startDateTime;
    }

    public function setStartDateTime(\DateTimeImmutable $startDateTime): self
    {
        $this->startDateTime = $startDateTime;

        return $this;
    }

    public function getEndDateTime(): ?\DateTimeImmutable
    {
        return $this->endDateTime;
    }

    public function setEndDateTime(\DateTimeImmutable $endDateTime): self
    {
        $this->endDateTime = $endDateTime;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(\DateTimeImmutable $reference): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $reference;
    }
}

