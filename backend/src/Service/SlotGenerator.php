<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\SlotHold;
use App\Repository\BookingRepository;
use App\Repository\ProviderWorkingHoursRepository;
use App\Repository\SlotHoldRepository;

class SlotGenerator
{
    private ?int $lastPurgeTimestamp = null;
    private const PURGE_INTERVAL_SECONDS = 5;

    public function __construct(
        private readonly ProviderWorkingHoursRepository $workingHoursRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly SlotHoldRepository $slotHoldRepository,
    ) {
    }

    /**
     * @return list<array{start: string, end: string, available: bool}>
     */
    public function generate(Provider $provider, Service $service, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($from > $to) {
            throw new \InvalidArgumentException('The "from" date must be earlier than or equal to the "to" date.');
        }

        $workingHours = $this->workingHoursRepository->findBy(['provider' => $provider]);
        if (count($workingHours) === 0) {
            return [];
        }

        $hoursByWeekday = [];
        foreach ($workingHours as $hour) {
            $hoursByWeekday[$hour->getWeekday()][] = $hour;
        }

        // Expand time window slightly to account for service duration at the end boundary.
        $searchStart = $from->setTime(0, 0);
        $searchEnd = $to->setTime(23, 59, 59);

        $this->purgeExpiredIfNeeded();

        $bookings = $this->bookingRepository->findActiveBookingsForProviderBetween(
            $provider->getId(),
            $searchStart,
            $searchEnd->modify('+1 minute')
        );

        $holds = $this->slotHoldRepository->findActiveHoldsForProviderBetween(
            $provider->getId(),
            $searchStart,
            $searchEnd->modify('+1 minute')
        );

        $serviceDuration = $service->getDurationMinutes() ?? 30;
        $slotLength = 30;

        $holdWindows = array_map(static fn (SlotHold $hold) => [
            'start' => $hold->getStartDateTime(),
            'end' => $hold->getEndDateTime(),
        ], $holds);

        $busyWindows = array_merge(array_map(static fn (Booking $booking) => [
            'start' => $booking->getStartDateTime(),
            'end' => $booking->getEndDateTime(),
        ], $bookings), $holdWindows);

        $result = [];
        $currentDate = $searchStart;
        while ($currentDate <= $to) {
            $weekday = self::weekdayFromDate($currentDate);
            if (!isset($hoursByWeekday[$weekday])) {
                $currentDate = $currentDate->modify('+1 day');
                continue;
            }

            foreach ($hoursByWeekday[$weekday] as $window) {
                $windowStart = self::combineDateAndTime($currentDate, $window->getStartTime());
                $windowEnd = self::combineDateAndTime($currentDate, $window->getEndTime());

                $slotStart = $windowStart;
                while ($slotStart < $windowEnd) {
                    $slotEnd = $slotStart->modify(sprintf('+%d minutes', $serviceDuration));
                    if ($slotEnd > $windowEnd || $slotEnd > $searchEnd) {
                        break;
                    }

                    if ($slotStart < $from) {
                        $slotStart = $slotStart->modify(sprintf('+%d minutes', $slotLength));
                        continue;
                    }

                    $isAvailable = !$this->overlaps($slotStart, $slotEnd, $busyWindows);
                    $result[] = [
                        'start' => $slotStart->format(\DateTimeInterface::ATOM),
                        'end' => $slotEnd->format(\DateTimeInterface::ATOM),
                        'available' => $isAvailable,
                    ];
                    $slotStart = $slotStart->modify(sprintf('+%d minutes', $slotLength));
                }
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        return $result;
    }

    private static function weekdayFromDate(\DateTimeImmutable $date): int
    {
        // Normalize to 0 (Monday) ... 6 (Sunday)
        return (int) $date->format('N') - 1;
    }

    private static function combineDateAndTime(\DateTimeImmutable $date, ?\DateTimeInterface $time): \DateTimeImmutable
    {
        $timeString = $time?->format('H:i:s') ?? '00:00:00';

        return \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $timeString,
            $date->getTimezone()
        ) ?: $date;
    }

    /**
     * @param list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $busyWindows
     */
    private function overlaps(\DateTimeImmutable $candidateStart, \DateTimeImmutable $candidateEnd, array $busyWindows): bool
    {
        foreach ($busyWindows as $window) {
            if ($candidateStart < $window['end'] && $window['start'] < $candidateEnd) {
                return true;
            }
        }

        return false;
    }
    private function purgeExpiredIfNeeded(): void
    {
        $now = new \DateTimeImmutable();
        if ($this->lastPurgeTimestamp !== null
            && ($now->getTimestamp() - $this->lastPurgeTimestamp) < self::PURGE_INTERVAL_SECONDS) {
            return;
        }

        $this->slotHoldRepository->purgeExpired($now);
        $this->lastPurgeTimestamp = $now->getTimestamp();
    }
}

