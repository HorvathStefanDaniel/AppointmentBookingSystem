<?php

namespace App\Controller;

use App\Http\ProblemResponseFactory;
use App\Repository\ProviderRepository;
use App\Repository\ServiceRepository;
use App\Service\SlotGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/providers', name: 'api_providers_')]
class ProviderController extends AbstractController
{
    public function __construct(
        private readonly ProviderRepository $providerRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly SlotGenerator $slotGenerator,
        private readonly ProblemResponseFactory $problems,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $providers = $this->providerRepository->findAll();

        $data = array_map(static fn ($provider) => [
            'id' => $provider->getId(),
            'name' => $provider->getName(),
        ], $providers);

        return $this->json($data);
    }

    #[Route('/{id}/slots', name: 'slots', methods: ['GET'])]
    public function slots(int $id, Request $request): JsonResponse
    {
        $provider = $this->providerRepository->find($id);
        if (null === $provider) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Provider not found.');
        }

        $serviceId = (int) $request->query->get('serviceId');
        if ($serviceId <= 0) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'Query parameter "serviceId" is required.');
        }

        $service = $this->serviceRepository->find($serviceId);
        if (null === $service) {
            return $this->problems->create(JsonResponse::HTTP_NOT_FOUND, 'Service not found.');
        }

        $fromParam = $request->query->get('from');
        $toParam = $request->query->get('to');

        $from = $fromParam
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $fromParam) ?: null
            : (new \DateTimeImmutable('today'))->setTime(0, 0);

        $to = $toParam
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $toParam) ?: null
            : $from?->modify('+30 days');

        if (null === $from || null === $to) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, 'Invalid date format. Expected Y-m-d.');
        }

        try {
            $slots = $this->slotGenerator->generate($provider, $service, $from, $to);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            return $this->problems->create(JsonResponse::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->json([
            'providerId' => $provider->getId(),
            'serviceId' => $serviceId,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'slots' => $slots,
        ]);
    }
}

