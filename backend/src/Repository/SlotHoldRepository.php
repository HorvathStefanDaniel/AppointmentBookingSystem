<?php

namespace App\Repository;

use App\Entity\SlotHold;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SlotHold>
 */
class SlotHoldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SlotHold::class);
    }

    /**
     * @return list<SlotHold>
     */
    public function findActiveHoldsForProviderBetween(int $providerId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('h')
            ->andWhere('h.provider = :providerId')
            ->andWhere('h.expiresAt > :now')
            ->andWhere('h.endDateTime > :from')
            ->andWhere('h.startDateTime < :to')
            ->setParameter('providerId', $providerId)
            ->setParameter('now', $now)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    public function findConflictingHold(int $providerId, \DateTimeImmutable $start, \DateTimeImmutable $end): ?SlotHold
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('h')
            ->andWhere('h.provider = :providerId')
            ->andWhere('h.expiresAt > :now')
            ->andWhere('h.endDateTime > :start')
            ->andWhere('h.startDateTime < :end')
            ->setParameter('providerId', $providerId)
            ->setParameter('now', $now)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function purgeExpired(\DateTimeImmutable $reference): void
    {
        $qb = $this->createQueryBuilder('h');

        $qb->delete()
            ->andWhere('h.expiresAt <= :now')
            ->setParameter('now', $reference)
            ->getQuery()
            ->execute();
    }
}

