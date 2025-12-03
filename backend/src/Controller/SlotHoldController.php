<?php

namespace App\Controller;

use App\Dto\Booking\BookingRequest;
use App\Entity\Provider;
use App\Entity\Service;
use App\Entity\SlotHold;
use App\Entity\User;
use App\Exception\SlotHoldConflictException;
use App\Http\ProblemResponseFactory;
use App\Repository\ProviderRepository;
use App\Repository\ServiceRepository;
use App\Repository\SlotHoldRepository;
use App\Service\SlotHoldManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/bookings/holds', name: 'api_booking_holds_')]
class SlotHoldController extends AbstractController
{
    public function __construct(
        private readonly ProviderRepository $providerRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly SlotHoldRepository $slotHoldRepository,
        private readonly SlotHoldManager $slotHoldManager,
        private readonly ValidatorInterface $validator,
        private readonly ProblemResponseFactory $problems,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUserOrThrow();

        $payload = $this->getBookingRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        /** @var Provider|null $provider */
        $provider = $this->providerRepository->find($payload->providerId);
        if (null === $provider) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Provider not found.');
        }

        if ($response = $this->assertProviderAccess($user, $provider)) {
            return $response;
        }

        /** @var Service|null $service */
        $service = $this->serviceRepository->find($payload->serviceId);
        if (null === $service) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Service not found.');
        }

        $start = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $payload->startDateTime ?? '');
        if (!$start) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'Invalid datetime format. Use ISO 8601.');
        }

        try {
            /** @var SlotHold $hold */
            $hold = $this->slotHoldManager->createHold($user, $provider, $service, $start);
        } catch (SlotHoldConflictException $exception) {
            return $this->problems->create(JsonResponse::HTTP_CONFLICT, $exception->getMessage());
        } catch (\RuntimeException $exception) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->json([
            'id' => $hold->getId(),
            'expiresAt' => $hold->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $hold = $this->slotHoldRepository->find($id);
        if (!$hold) {
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }

        $user = $this->getUserOrThrow();
        $isOwner = $hold->getUser()?->getId() === $user->getId();
        $isAdmin = in_array('R_ADMIN', $user->getRoles(), true);

        if (!$isOwner && !$isAdmin) {
            return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'Access denied.');
        }

        $this->slotHoldManager->releaseHold($hold);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function getUserOrThrow(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Authenticated user expected.');
        }

        return $user;
    }

    private function assertProviderAccess(User $user, Provider $provider): ?JsonResponse
    {
        $hasProviderRole = in_array('R_PROVIDER', $user->getRoles(), true);
        $isAdmin = in_array('R_ADMIN', $user->getRoles(), true);

        if ($hasProviderRole && !$isAdmin) {
            $userProvider = $user->getProvider();
            if (null === $userProvider || $userProvider->getId() !== $provider->getId()) {
                return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'You can only reserve slots for your assigned provider.');
            }
        }

        return null;
    }

    private function getBookingRequest(Request $request): BookingRequest|JsonResponse
    {
        try {
            $payload = new BookingRequest();
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'Invalid JSON payload.');
        }
        $payload->providerId = $data['providerId'] ?? null;
        $payload->serviceId = $data['serviceId'] ?? null;
        $payload->startDateTime = $data['startDateTime'] ?? null;

        $violations = $this->validator->validate($payload);
        if (count($violations) > 0) {
            return $this->problems->validationFailed($violations);
        }

        return $payload;
    }
}

