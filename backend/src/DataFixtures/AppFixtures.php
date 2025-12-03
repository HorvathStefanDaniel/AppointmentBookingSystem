<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Provider;
use App\Entity\ProviderWorkingHours;
use App\Entity\Service;
use App\Entity\User;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $providers = $this->createProviders($manager);
        $services = $this->createServices($manager);
        $this->createUsers($manager, $providers);
        $this->createSampleBookings($manager, $providers, $services);

        $manager->flush();
    }

    /**
     * @return Provider[]
     */
    private function createProviders(ObjectManager $manager): array
    {
        $providerData = [
            'Alpha Marina' => ['baseHour' => 9],
            'Bravo Harbour' => ['baseHour' => 10],
        ];

        $providers = [];

        foreach ($providerData as $name => $config) {
            $provider = new Provider();
            $provider->setName($name);
            $manager->persist($provider);

            // 7 days of working hours 09:00–17:00 (or configured)
            for ($weekday = 0; $weekday < 7; ++$weekday) {
                $hours = new ProviderWorkingHours();
                $hours->setProvider($provider);
                $hours->setWeekday($weekday);
                $hours->setStartTime(new DateTime(sprintf('%02d:00', $config['baseHour'])));
                $hours->setEndTime(new DateTime(sprintf('%02d:00', $config['baseHour'] + 8)));

                $manager->persist($hours);
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    /**
     * @return Service[]
     */
    private function createServices(ObjectManager $manager): array
    {
        $serviceConfigs = [
            ['name' => 'Standard Check', 'duration' => 30],
            ['name' => 'Extended Maintenance', 'duration' => 60],
            ['name' => 'Deep Clean', 'duration' => 90],
        ];

        $services = [];
        foreach ($serviceConfigs as $config) {
            $service = new Service();
            $service->setName($config['name']);
            $service->setDurationMinutes($config['duration']);
            $manager->persist($service);
            $services[] = $service;
        }

        return $services;
    }

    /**
     * @param Provider[] $providers
     */
    private function createUsers(ObjectManager $manager, array $providers): void
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['R_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '12344321'));
        $manager->persist($admin);

        $postmanAdmin = new User();
        $postmanAdmin->setEmail('postman@example.com');
        $postmanAdmin->setRoles(['R_ADMIN']);
        $postmanAdmin->setPassword($this->passwordHasher->hashPassword($postmanAdmin, 'postman1'));
        $manager->persist($postmanAdmin);

        $providerUser = new User();
        $providerUser->setEmail('provider@example.com');
        $providerUser->setRoles(['R_PROVIDER']);
        $providerUser->setProvider($providers[0]);
        $providerUser->setPassword($this->passwordHasher->hashPassword($providerUser, '12344321'));
        $manager->persist($providerUser);

        foreach (['consumer1@example.com', 'consumer2@example.com'] as $email) {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['R_CONSUMER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, '12344321'));
            $manager->persist($user);
        }
    }

    /**
     * @param Provider[] $providers
     * @param Service[]  $services
     */
    private function createSampleBookings(ObjectManager $manager, array $providers, array $services): void
    {
        $userRepo = $manager->getRepository(User::class);
        /** @var User $consumer */
        $consumer = $userRepo->findOneBy(['email' => 'consumer1@example.com']);

        if (!$consumer) {
            return;
        }

        foreach ($providers as $provider) {
            foreach (array_slice($services, 0, 2) as $offset => $service) {
                $start = (new DateTimeImmutable('tomorrow'))
                    ->setTime(9 + ($offset * 2), 0);

                $booking = new Booking();
                $booking->setUser($consumer);
                $booking->setProvider($provider);
                $booking->setService($service);
                $booking->setStartDateTime($start);
                $booking->setEndDateTime($start->add(new DateInterval(sprintf('PT%dM', $service->getDurationMinutes()))));
                $booking->setStatus(Booking::STATUS_ACTIVE);

                $manager->persist($booking);
            }
        }

        $janStart = new DateTimeImmutable('2026-01-05 09:00:00');
        $providerCount = count($providers);
        $serviceCount = count($services);

        for ($i = 0; $i < 10; ++$i) {
            $provider = $providers[$i % $providerCount];
            $service = $services[$i % $serviceCount];
            $start = $janStart->modify(sprintf('+%d days', $i));

            $booking = new Booking();
            $booking->setUser($consumer);
            $booking->setProvider($provider);
            $booking->setService($service);
            $booking->setStartDateTime($start);
            $booking->setEndDateTime($start->add(new DateInterval(sprintf('PT%dM', $service->getDurationMinutes()))));
            $booking->setStatus(Booking::STATUS_ACTIVE);

            $manager->persist($booking);
        }
    }
}
