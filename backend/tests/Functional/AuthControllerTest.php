<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use const JSON_THROW_ON_ERROR;

final class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);

        $this->purgeUsers();
    }

    public function testRegisterCreatesUser(): void
    {
        $payload = [
            'email' => 'test-user@example.com',
            'password' => 'Secret123!',
        ];

        $this->requestJson('POST', '/api/auth/register', $payload);

        self::assertResponseStatusCodeSame(201);

        $user = $this->userRepository->findOneBy(['email' => $payload['email']]);
        self::assertNotNull($user);
        self::assertSame(['R_CONSUMER'], $user->getRoles(), 'New users default to consumer role');
    }

    public function testLoginReturnsJwt(): void
    {
        $user = new User();
        $user->setEmail('login@example.com');
        $user->setRoles(['R_CONSUMER']);
        $user->setPassword($this->hasher->hashPassword($user, 'Secret123!'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->requestJson('POST', '/api/auth/login', ['email' => 'login@example.com', 'password' => 'Secret123!']);

        self::assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $payload);
        self::assertIsString($payload['token']);
        self::assertSame('Bearer', $payload['token_type'] ?? 'Bearer'); // Lexik default
    }

    private function purgeUsers(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM `booking`');
        $connection->executeStatement('DELETE FROM `user`');
    }

    private function requestJson(string $method, string $uri, array $payload): void
    {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }
}

