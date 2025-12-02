<?php

namespace App\Controller;

use App\Dto\Auth\RegisterRequest;
use App\Entity\User;
use App\Http\ProblemResponseFactory;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly ProblemResponseFactory $problems,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            /** @var RegisterRequest $payload */
            $payload = $this->serializer->deserialize($request->getContent(), RegisterRequest::class, JsonEncoder::FORMAT);
        } catch (NotEncodableValueException) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'Invalid JSON payload.');
        }

        $violations = $this->validator->validate($payload);
        if (count($violations) > 0) {
            return $this->problems->validationFailed($violations);
        }

        $existing = $this->userRepository->findOneBy(['email' => mb_strtolower($payload->email ?? '')]);
        if ($existing instanceof User) {
            return $this->problems->create(JsonResponse::HTTP_CONFLICT, 'User already exists.');
        }

        $user = new User();
        $user->setEmail($payload->email ?? '');
        $user->setRoles(['R_CONSUMER']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $payload->password ?? '');
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Registration successful. You can now log in.',
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): void
    {
        throw new \LogicException('This code should be intercepted by the security firewall.');
    }
}

