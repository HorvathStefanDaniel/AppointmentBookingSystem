<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\User;
use App\Service\BookingManager;
use App\Service\SlotHoldManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BookingManagerTest extends KernelTestCase
{
    private BookingManager $bookingManager;
    private SlotHoldManager $slotHoldManager;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->bookingManager = $container->get(BookingManager::class);
        $this->slotHoldManager = $container->get(SlotHoldManager::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $this->purgeDatabase();
    }

    public function testBookFromHoldCreatesBookingAndRemovesHold(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $service = $this->createService(60);
        $start = new \DateTimeImmutable('+1 day 11:00');

        $hold = $this->slotHoldManager->createHold($user, $provider, $service, $start);
        $holdId = $hold->getId();
        self::assertNotNull($holdId);

        $booking = $this->bookingManager->bookFromHold($user, $hold);

        self::assertNotNull($booking->getId());
        self::assertSame($user->getId(), $booking->getUser()?->getId());
        self::assertSame($provider->getId(), $booking->getProvider()?->getId());

        $this->entityManager->clear();

        $bookingFromDb = $this->entityManager->getRepository(Booking::class)->find($booking->getId());
        self::assertNotNull($bookingFromDb);

        $holdFromDb = $this->entityManager->getRepository(\App\Entity\SlotHold::class)->find($holdId);
        self::assertNull($holdFromDb, 'Hold should be removed after successful booking.');
    }

    public function testBookFromHoldFailsForExpiredHold(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $service = $this->createService();
        $start = new \DateTimeImmutable('+2 days 15:00');

        $hold = $this->slotHoldManager->createHold($user, $provider, $service, $start);
        $hold->setExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reservation has expired.');

        $this->bookingManager->bookFromHold($user, $hold);
    }

    private function purgeDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['slot_hold', 'booking', '`user`', 'service', 'provider'] as $table) {
            $connection->executeStatement(sprintf('DELETE FROM %s', $table));
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createProvider(string $name = 'Provider'): Provider
    {
        $provider = new Provider();
        $provider->setName(sprintf('%s %s', $name, uniqid()));

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        return $provider;
    }

    private function createService(int $duration = 30): Service
    {
        $service = new Service();
        $service->setName('Service '.uniqid());
        $service->setDurationMinutes($duration);

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $service;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail(uniqid('user', true).'@example.com');
        $user->setPassword('password');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}

