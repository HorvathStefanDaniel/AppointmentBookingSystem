<?php

namespace App\Service;

use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\SlotHold;
use App\Entity\User;
use App\Exception\SlotHoldConflictException;
use App\Repository\BookingRepository;
use App\Repository\SlotHoldRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class SlotHoldManager
{
    private const DEFAULT_TTL_SECONDS = 60;
    private const PURGE_INTERVAL_SECONDS = 5;

    private ?int $lastPurgeTimestamp = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SlotHoldRepository $slotHoldRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    public function createHold(User $user, Provider $provider, Service $service, \DateTimeImmutable $startDateTime): SlotHold
    {
        $now = new \DateTimeImmutable();
        $this->purgeExpiredIfNeeded($now);

        $endDateTime = $startDateTime->modify(sprintf('+%d minutes', $service->getDurationMinutes() ?? 30));
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->ttlSeconds));

        if ($this->slotHoldRepository->findConflictingHold($provider->getId(), $startDateTime, $endDateTime)) {
            throw SlotHoldConflictException::alreadyReserved();
        }

        if ($this->bookingRepository->findConflictingActiveBooking($provider->getId(), $startDateTime, $endDateTime)) {
            throw SlotHoldConflictException::alreadyBooked();
        }

        $hold = new SlotHold();
        $hold->setProvider($provider)
            ->setService($service)
            ->setUser($user)
            ->setStartDateTime($startDateTime)
            ->setEndDateTime($endDateTime)
            ->setExpiresAt($expiresAt);

        try {
            $this->entityManager->persist($hold);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw SlotHoldConflictException::alreadyReserved();
        }

        return $hold;
    }

    public function releaseHold(SlotHold $hold): void
    {
        $this->entityManager->remove($hold);
        $this->entityManager->flush();
    }

    private function purgeExpiredIfNeeded(\DateTimeImmutable $reference): void
    {
        if ($this->lastPurgeTimestamp !== null
            && ($reference->getTimestamp() - $this->lastPurgeTimestamp) < self::PURGE_INTERVAL_SECONDS) {
            return;
        }

        $this->slotHoldRepository->purgeExpired($reference);
        $this->lastPurgeTimestamp = $reference->getTimestamp();
    }
}

