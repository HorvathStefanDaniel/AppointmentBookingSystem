<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\User;
use App\Exception\SlotHoldConflictException;
use App\Service\SlotHoldManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SlotHoldManagerTest extends KernelTestCase
{
    private SlotHoldManager $slotHoldManager;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->slotHoldManager = $container->get(SlotHoldManager::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $this->purgeDatabase();
    }

    public function testCreateHoldPersistsEntityAndSetsExpiry(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $service = $this->createService(45);
        $start = new \DateTimeImmutable('+1 day 09:00');

        $hold = $this->slotHoldManager->createHold($user, $provider, $service, $start);

        self::assertNotNull($hold->getId());
        self::assertSame($provider->getId(), $hold->getProvider()?->getId());
        self::assertSame($service->getId(), $hold->getService()?->getId());

        $expectedEnd = $start->modify(sprintf('+%d minutes', $service->getDurationMinutes() ?? 0));
        self::assertSame($expectedEnd->getTimestamp(), $hold->getEndDateTime()?->getTimestamp());

        $now = new \DateTimeImmutable();
        self::assertGreaterThan($now, $hold->getExpiresAt());
        self::assertLessThanOrEqual($now->modify('+65 seconds'), $hold->getExpiresAt());
    }

    public function testCreateHoldRejectsConflictingReservation(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $service = $this->createService();
        $start = new \DateTimeImmutable('+2 days 10:00');

        $this->slotHoldManager->createHold($user, $provider, $service, $start);

        $this->expectException(SlotHoldConflictException::class);
        $this->expectExceptionMessage('Slot already reserved.');

        $this->slotHoldManager->createHold($user, $provider, $service, $start);
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

    private function createProvider(string $name = 'Test Provider'): Provider
    {
        $provider = new Provider();
        $provider->setName(sprintf('%s %s', $name, uniqid()));

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        return $provider;
    }

    private function createService(int $durationMinutes = 30): Service
    {
        $service = new Service();
        $service->setName('Service '.uniqid());
        $service->setDurationMinutes($durationMinutes);

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $service;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail(uniqid('user', true).'@example.com');
        $user->setPassword('secret');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}

