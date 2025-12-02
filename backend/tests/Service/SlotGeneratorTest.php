<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\ProviderWorkingHours;
use App\Entity\Service;
use App\Entity\User;
use App\Service\SlotGenerator;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SlotGeneratorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SlotGenerator $generator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->generator = $container->get(SlotGenerator::class);

        $this->purgeTables();
    }

    public function testGenerateReturnsAvailableSlots(): void
    {
        $provider = $this->createProvider('Harba Marina');
        $service = $this->createService('30-min service', 30);
        $this->addWorkingHours($provider, 0, '09:00', '10:00'); // Monday 09–10

        $from = new DateTimeImmutable('next monday 00:00');
        $to = $from->add(new DateInterval('P0D'));

        $slots = $this->generator->generate($provider, $service, $from, $to);

        self::assertCount(2, $slots);
        self::assertTrue($slots[0]['available']);
        self::assertTrue($slots[1]['available']);
        self::assertSame($from->setTime(9, 0)->format(DATE_ATOM), $slots[0]['start']);
        self::assertSame($from->setTime(9, 30)->format(DATE_ATOM), $slots[1]['start']);
    }

    public function testGenerateSkipsConflictingBookings(): void
    {
        $provider = $this->createProvider('Overlap Marina');
        $service = $this->createService('Hourly Service', 60);
        $this->addWorkingHours($provider, 1, '13:00', '15:00'); // Tuesday 13–15

        $start = new DateTimeImmutable('next tuesday 13:00');
        $user = $this->createUser('booking-owner@example.com', $provider);
        $this->createBooking($user, $provider, $service, $start, $start->add(new DateInterval('PT1H')));

        $slots = $this->generator->generate(
            $provider,
            $service,
            $start,
            $start->add(new DateInterval('P0D'))
        );

        self::assertCount(3, $slots);
        self::assertFalse($slots[0]['available']);
        self::assertFalse($slots[1]['available']);
        self::assertTrue($slots[2]['available']);
        self::assertSame($start->format(DATE_ATOM), $slots[0]['start']);
        self::assertSame($start->add(new DateInterval('PT1H'))->format(DATE_ATOM), $slots[2]['start']);
    }

    private function createProvider(string $name): Provider
    {
        $provider = new Provider();
        $provider->setName($name);
        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        return $provider;
    }

    private function createService(string $name, int $duration): Service
    {
        $service = new Service();
        $service->setName($name);
        $service->setDurationMinutes($duration);
        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $service;
    }

    private function addWorkingHours(Provider $provider, int $weekday, string $start, string $end): void
    {
        $workingHours = new ProviderWorkingHours();
        $workingHours->setProvider($provider);
        $workingHours->setWeekday($weekday);
        $workingHours->setStartTime(new DateTime($start));
        $workingHours->setEndTime(new DateTime($end));
        $this->entityManager->persist($workingHours);
        $this->entityManager->flush();
    }

    private function createUser(string $email, Provider $provider): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['R_CONSUMER']);
        $user->setPassword('test'); // not used
        $user->setProvider($provider);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createBooking(User $user, Provider $provider, Service $service, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $booking = new Booking();
        $booking->setUser($user);
        $booking->setProvider($provider);
        $booking->setService($service);
        $booking->setStartDateTime($start);
        $booking->setEndDateTime($end);
        $booking->setStatus(Booking::STATUS_ACTIVE);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();
    }

    private function purgeTables(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM booking');
        $connection->executeStatement('DELETE FROM service');
        $connection->executeStatement('DELETE FROM provider_working_hours');
        $connection->executeStatement('DELETE FROM `user`');
        $connection->executeStatement('DELETE FROM provider');
    }
}

