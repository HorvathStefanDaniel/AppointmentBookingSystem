<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return list<Booking>
     */
    public function findActiveBookingsForProviderBetween(int $providerId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.provider = :providerId')
            ->andWhere('b.status = :status')
            ->andWhere('b.endDateTime > :from')
            ->andWhere('b.startDateTime < :to')
            ->setParameter('providerId', $providerId)
            ->setParameter('status', Booking::STATUS_ACTIVE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    public function findConflictingActiveBooking(int $providerId, \DateTimeImmutable $start, \DateTimeImmutable $end, LockMode|int $lockMode = LockMode::NONE): ?Booking
    {
        $query = $this->createQueryBuilder('b')
            ->andWhere('b.provider = :providerId')
            ->andWhere('b.status = :status')
            ->andWhere('b.endDateTime > :start')
            ->andWhere('b.startDateTime < :end')
            ->setParameter('providerId', $providerId)
            ->setParameter('status', Booking::STATUS_ACTIVE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1)
            ->getQuery();

        $isNone = $lockMode instanceof LockMode ? $lockMode === LockMode::NONE : $lockMode === 0;

        if (! $isNone) {
            $query->setLockMode($lockMode);
        }

        return $query->getOneOrNullResult();
    }

    /**
     * @return list<Booking>
     */
    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.startDateTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Booking>
     */
    public function findByProviderOrdered(int $providerId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.provider = :providerId')
            ->setParameter('providerId', $providerId)
            ->orderBy('b.startDateTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Booking>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.startDateTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
