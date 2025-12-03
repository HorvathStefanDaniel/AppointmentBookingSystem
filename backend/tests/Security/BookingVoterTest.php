<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\User;
use App\Security\Voter\BookingVoter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class BookingVoterTest extends TestCase
{
    private BookingVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new BookingVoter();
    }

    public function testAdminCanManageAnyBooking(): void
    {
        $booking = $this->createBooking($this->createUser(200), $this->createProvider(10));
        $admin = $this->createUser(1, ['R_ADMIN']);

        $result = $this->voter->vote($this->createToken($admin), $booking, [BookingVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testProviderCanManageBookingsForOwnProvider(): void
    {
        $provider = $this->createProvider(42);
        $booking = $this->createBooking($this->createUser(301), $provider);

        $providerUser = $this->createUser(500, ['R_PROVIDER']);
        $providerUser->setProvider($provider);

        $result = $this->voter->vote($this->createToken($providerUser), $booking, [BookingVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testProviderCannotManageBookingsForOtherProviders(): void
    {
        $bookingProvider = $this->createProvider(55);
        $booking = $this->createBooking($this->createUser(600), $bookingProvider);

        $foreignProvider = $this->createProvider(77);
        $providerUser = $this->createUser(800, ['R_PROVIDER']);
        $providerUser->setProvider($foreignProvider);

        $result = $this->voter->vote($this->createToken($providerUser), $booking, [BookingVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    private function createBooking(User $owner, Provider $provider): Booking
    {
        $service = new Service();
        $service->setName('Consultation')->setDurationMinutes(30);

        $booking = new Booking();
        $booking->setUser($owner)
            ->setProvider($provider)
            ->setService($service)
            ->setStartDateTime(new DateTimeImmutable('2025-01-01 09:00:00'))
            ->setEndDateTime(new DateTimeImmutable('2025-01-01 09:30:00'));

        return $booking;
    }

    private function createProvider(int $id): Provider
    {
        $provider = new Provider();
        $provider->setName('Provider '.$id);
        $this->setEntityId($provider, $id);

        return $provider;
    }

    private function createUser(int $id, array $roles = ['R_CONSUMER']): User
    {
        $user = new User();
        $user->setEmail(sprintf('user%d@example.com', $id));
        $user->setRoles($roles);
        $this->setEntityId($user, $id);

        return $user;
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $property = new ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}

