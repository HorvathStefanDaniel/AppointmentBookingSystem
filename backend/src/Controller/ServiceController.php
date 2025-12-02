<?php

namespace App\Controller;

use App\Dto\Service\ServiceRequest;
use App\Dto\Service\ServiceUpdateRequest;
use App\Entity\Service;
use App\Entity\User;
use App\Http\ProblemResponseFactory;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/services', name: 'api_services_')]
class ServiceController extends AbstractController
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly ProblemResponseFactory $problems,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $services = $this->serviceRepository->findAll();

        return $this->json(array_map(fn (Service $service) => $this->transformService($service), $services));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->assertAdmin();

        $payload = $this->deserializeRequest($request, ServiceRequest::class);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $this->validator->validate($payload);
        if (count($violations) > 0) {
            return $this->problems->validationFailed($violations);
        }

        $service = new Service();
        $service->setName($payload->name ?? '');
        $service->setDurationMinutes($payload->durationMinutes ?? 30);

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $this->json($this->transformService($service), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $service = $this->serviceRepository->find($id);
        if (null === $service) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Service not found.');
        }

        $this->assertAdmin();

        $payload = $this->deserializeRequest($request, ServiceUpdateRequest::class);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $this->validator->validate($payload);
        if (count($violations) > 0) {
            return $this->problems->validationFailed($violations);
        }

        if ($payload->name !== null) {
            $service->setName($payload->name);
        }

        if ($payload->durationMinutes !== null) {
            $service->setDurationMinutes($payload->durationMinutes);
        }

        $this->entityManager->flush();

        return $this->json($this->transformService($service));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $service = $this->serviceRepository->find($id);
        if (null === $service) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Service not found.');
        }

        $this->assertAdmin();

        $this->entityManager->remove($service);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function deserializeRequest(Request $request, string $class): ServiceRequest|ServiceUpdateRequest|JsonResponse
    {
        try {
            $payload = $this->serializer->deserialize($request->getContent(), $class, JsonEncoder::FORMAT);

            return $payload;
        } catch (NotEncodableValueException) {
            return $this->json(['message' => 'Invalid JSON payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    private function assertAdmin(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!in_array('R_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Admin privileges required.');
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Authenticated user expected.');
        }

        return $user;
    }

    private function transformService(Service $service): array
    {
        return [
            'id' => $service->getId(),
            'name' => $service->getName(),
            'durationMinutes' => $service->getDurationMinutes(),
        ];
    }

}

