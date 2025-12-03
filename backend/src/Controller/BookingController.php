<?php

namespace App\Controller;

use App\Dto\Booking\BookingRequest;
use App\Entity\Booking;
use App\Entity\User;
use App\Entity\Provider;
use App\Repository\BookingRepository;
use App\Http\ProblemResponseFactory;
use App\Repository\ProviderRepository;
use App\Repository\ServiceRepository;
use App\Repository\SlotHoldRepository;
use App\Service\BookingManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/bookings', name: 'api_bookings_')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingManager $bookingManager,
        private readonly BookingRepository $bookingRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly ProviderRepository $providerRepository,
        private readonly SlotHoldRepository $slotHoldRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        #[Autowire(service: 'limiter.booking_create')]
        private readonly RateLimiterFactory $bookingLimiter,
        private readonly ProblemResponseFactory $problems,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        $limit = $this->bookingLimiter->create($this->rateLimitKey($request))->consume(1);
        if (!$limit->isAccepted()) {
            return $this->problems->create(
                JsonResponse::HTTP_TOO_MANY_REQUESTS,
                'Too many booking attempts. Please try again later.',
                'rate_limited',
                [
                    'retryAfter' => $limit->getRetryAfter()?->getTimestamp(),
                    'remainingTokens' => $limit->getRemainingTokens(),
                ]
            );
        }

        $payload = $this->deserializeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $violations = $this->validator->validate($payload);
        if (count($violations) > 0) {
            return $this->problems->validationFailed($violations);
        }

        if ($payload->holdId) {
            return $this->createFromHold($payload->holdId);
        }

        $service = $this->serviceRepository->find($payload->serviceId);
        if (null === $service) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Service not found.');
        }

        $provider = $this->providerRepository->find($payload->providerId);
        if (null === $provider) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Provider not found.');
        }

        if ($response = $this->assertProviderBookingAccess($user, $provider)) {
            return $response;
        }

        $start = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $payload->startDateTime ?? '');
        if (!$start) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'Invalid datetime format. Use ISO 8601.');
        }

        try {
            $booking = $this->bookingManager->book($user, $service, $provider, $start);
        } catch (\RuntimeException $exception) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->json($this->transformBooking($booking), JsonResponse::HTTP_CREATED);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function myBookings(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $bookings = $this->bookingRepository->findByUserOrdered($user);

        return $this->json(array_map(fn (Booking $booking) => $this->transformBooking($booking), $bookings));
    }

    #[Route('', name: 'list_all', methods: ['GET'])]
    public function allBookings(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!in_array('R_ADMIN', $user->getRoles(), true)) {
            return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'Access denied.');
        }

        $bookings = $this->bookingRepository->findAllOrdered();

        return $this->json(array_map(fn (Booking $booking) => $this->transformBooking($booking), $bookings));
    }

    #[Route('/{id}', name: 'cancel', methods: ['DELETE'])]
    public function cancel(int $id): JsonResponse
    {
        $booking = $this->bookingRepository->find($id);
        if (null === $booking) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Booking not found.');
        }

        if (!$this->isGranted('BOOKING_MANAGE', $booking)) {
            return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'Access denied.');
        }

        try {
            $updated = $this->bookingManager->cancel($booking);
        } catch (\RuntimeException $exception) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->json($this->transformBooking($updated));
    }

    #[Route('/providers/{providerId}', name: 'by_provider', methods: ['GET'], requirements: ['providerId' => '\d+'])]
    public function providerBookings(int $providerId): JsonResponse
    {
        $provider = $this->providerRepository->find($providerId);
        if (null === $provider) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Provider not found.');
        }

        $user = $this->getAuthenticatedUser();
        $isAdmin = in_array('R_ADMIN', $user->getRoles(), true);
        $isProviderOwner = in_array('R_PROVIDER', $user->getRoles(), true) && $user->getProvider()?->getId() === $providerId;

        if (!$isAdmin && !$isProviderOwner) {
            return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'Access denied.');
        }

        $bookings = $this->bookingRepository->findByProviderOrdered($providerId);

        return $this->json(array_map(fn (Booking $booking) => $this->transformBooking($booking), $bookings));
    }

    #[Route('/providers/me', name: 'by_provider_self', methods: ['GET'])]
    public function providerBookingsForCurrentUser(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!in_array('R_PROVIDER', $user->getRoles(), true)) {
            return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'Provider role required.');
        }

        $provider = $user->getProvider();
        if (null === $provider) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'You are not assigned to a provider.');
        }

        $bookings = $this->bookingRepository->findByProviderOrdered($provider->getId());

        return $this->json(array_map(fn (Booking $booking) => $this->transformBooking($booking), $bookings));
    }

    private function deserializeRequest(Request $request): BookingRequest|JsonResponse
    {
        try {
            /** @var BookingRequest $payload */
            $payload = $this->serializer->deserialize($request->getContent(), BookingRequest::class, JsonEncoder::FORMAT);

            return $payload;
        } catch (NotEncodableValueException) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'Invalid JSON payload.');
        }
    }

    private function createFromHold(int $holdId): JsonResponse
    {
        $hold = $this->slotHoldRepository->find($holdId);
        if (null === $hold) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Reservation not found.');
        }

        $user = $this->getAuthenticatedUser();
        if ($hold->getUser()?->getId() !== $user->getId()) {
            return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'Reservation does not belong to you.');
        }

        try {
            $booking = $this->bookingManager->bookFromHold($user, $hold);
        } catch (\RuntimeException $exception) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->json($this->transformBooking($booking), JsonResponse::HTTP_CREATED);
    }

    private function transformBooking(Booking $booking): array
    {
        return [
            'id' => $booking->getId(),
            'status' => $booking->getStatus(),
            'startDateTime' => $booking->getStartDateTime()?->format(\DateTimeInterface::ATOM),
            'endDateTime' => $booking->getEndDateTime()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $booking->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'cancelledAt' => $booking->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            'service' => [
                'id' => $booking->getService()?->getId(),
                'name' => $booking->getService()?->getName(),
            ],
            'provider' => [
                'id' => $booking->getProvider()?->getId(),
                'name' => $booking->getProvider()?->getName(),
            ],
            'user' => [
                'id' => $booking->getUser()?->getId(),
                'email' => $booking->getUser()?->getEmail(),
            ],
        ];
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Authenticated user expected.');
        }

        return $user;
    }

    private function rateLimitKey(Request $request): string
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return sprintf('booking_%d', $user->getId());
        }

        return sprintf('booking_ip_%s', $request->getClientIp() ?? 'unknown');
    }
    private function assertProviderBookingAccess(User $user, Provider $provider): ?JsonResponse
    {
        $hasProviderRole = in_array('R_PROVIDER', $user->getRoles(), true);
        $isAdmin = in_array('R_ADMIN', $user->getRoles(), true);

        if ($hasProviderRole && !$isAdmin) {
            $userProvider = $user->getProvider();
            if (null === $userProvider || $userProvider->getId() !== $provider->getId()) {
                return $this->problems->create(JsonResponse::HTTP_FORBIDDEN, 'You can only book with your assigned provider.');
            }
        }

        return null;
    }
}

