<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\BookingRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class BookingManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
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
}

