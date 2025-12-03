<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\SlotHold;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\SlotHoldRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class BookingManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
        private readonly SlotHoldRepository $slotHoldRepository,
    ) {
    }

    public function book(User $user, Service $service, Provider $provider, \DateTimeImmutable $startDateTime): Booking
    {
        $durationMinutes = $service->getDurationMinutes() ?? 30;
        if ($durationMinutes <= 0) {
            throw new \RuntimeException('Service duration must be positive.');
        }

        if ($startDateTime < new \DateTimeImmutable()) {
            throw new \RuntimeException('Cannot book a slot in the past.');
        }

        if (((int) $startDateTime->format('i')) % 30 !== 0) {
            throw new \RuntimeException('Start time must align to 30-minute increments.');
        }

        $endDateTime = $startDateTime->modify(sprintf('+%d minutes', $durationMinutes));

        return $this->entityManager->wrapInTransaction(function () use ($user, $service, $provider, $startDateTime, $endDateTime) {
            if ($this->slotHoldRepository->findConflictingHold($provider->getId(), $startDateTime, $endDateTime)) {
                throw new \RuntimeException('Slot is currently reserved.');
            }
            $conflict = $this->bookingRepository->findConflictingActiveBooking(
                $provider->getId(),
                $startDateTime,
                $endDateTime,
                LockMode::PESSIMISTIC_WRITE
            );

            if ($conflict instanceof Booking) {
                throw new \RuntimeException('Slot already booked.');
            }

            $booking = new Booking();
            $booking->setService($service);
            $booking->setProvider($provider);
            $booking->setUser($user);
            $booking->setStartDateTime($startDateTime);
            $booking->setEndDateTime($endDateTime);
            $booking->setStatus(Booking::STATUS_ACTIVE);

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            return $booking;
        });
    }

    public function cancel(Booking $booking): Booking
    {
        if (!$booking->isActive()) {
            throw new \RuntimeException('Booking is already cancelled.');
        }

        $booking->setStatus(Booking::STATUS_CANCELLED);
        $booking->setCancelledAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $booking;
    }

    public function bookFromHold(User $user, SlotHold $hold): Booking
    {
        return $this->entityManager->wrapInTransaction(function () use ($user, $hold) {
            $now = new \DateTimeImmutable();

            $this->entityManager->lock($hold, LockMode::PESSIMISTIC_WRITE);

            if ($hold->isExpired($now)) {
                throw new \RuntimeException('Reservation has expired.');
            }

            if ($hold->getUser()?->getId() !== $user->getId()) {
                throw new \RuntimeException('Reservation does not belong to you.');
            }

            $service = $hold->getService();
            $provider = $hold->getProvider();
            $startDateTime = $hold->getStartDateTime();

            if (!$service || !$provider || !$startDateTime) {
                throw new \RuntimeException('Reservation is invalid.');
            }

            $durationMinutes = $service->getDurationMinutes() ?? 30;
            $endDateTime = $startDateTime->modify(sprintf('+%d minutes', $durationMinutes));

            $conflict = $this->bookingRepository->findConflictingActiveBooking(
                $provider->getId(),
                $startDateTime,
                $endDateTime,
                LockMode::PESSIMISTIC_WRITE
            );

            if ($conflict instanceof Booking) {
                throw new \RuntimeException('Slot already booked.');
            }

            $booking = new Booking();
            $booking->setService($service);
            $booking->setProvider($provider);
            $booking->setUser($user);
            $booking->setStartDateTime($startDateTime);
            $booking->setEndDateTime($endDateTime);
            $booking->setStatus(Booking::STATUS_ACTIVE);

            $this->entityManager->persist($booking);
            $this->entityManager->remove($hold);
            $this->entityManager->flush();

            return $booking;
        });
    }
}

