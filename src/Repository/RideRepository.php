<?php

namespace App\Repository;

use App\Entity\Hotel;
use App\Entity\Driver;
use App\Entity\Ride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ride::class);
    }

    public function findByHotel(Hotel $hotel, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.hotel = :hotel')
            ->setParameter('hotel', $hotel)
            ->orderBy('r.pickupDatetime', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByDriver(Driver $driver, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.driver = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('r.pickupDatetime', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :pending')
            ->setParameter('pending', Ride::STATUS_PENDING)
            ->orderBy('r.pickupDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.pickupDatetime >= :now')
            ->andWhere('r.status NOT IN (:done)')
            ->setParameter('now', new \DateTime())
            ->setParameter('done', [Ride::STATUS_COMPLETED, Ride::STATUS_CANCELLED])
            ->orderBy('r.pickupDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findForMonth(Hotel $hotel, \DateTimeInterface $month): array
    {
        $start = new \DateTime($month->format('Y-m-01 00:00:00'));
        $end   = new \DateTime($month->format('Y-m-t 23:59:59'));

        return $this->createQueryBuilder('r')
            ->where('r.hotel = :hotel')
            ->andWhere('r.status = :completed')
            ->andWhere('r.pickupDatetime BETWEEN :start AND :end')
            ->setParameter('hotel', $hotel)
            ->setParameter('completed', Ride::STATUS_COMPLETED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.pickupDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsForAdmin(): array
    {
        $qb = $this->createQueryBuilder('r');

        $total     = (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $pending   = (clone $qb)->select('COUNT(r.id)')->where('r.status = :s')->setParameter('s', Ride::STATUS_PENDING)->getQuery()->getSingleScalarResult();
        $active    = (clone $qb)->select('COUNT(r.id)')->where('r.status IN (:s)')->setParameter('s', [Ride::STATUS_ASSIGNED, Ride::STATUS_CONFIRMED, Ride::STATUS_IN_PROGRESS])->getQuery()->getSingleScalarResult();
        $completed = (clone $qb)->select('COUNT(r.id)')->where('r.status = :s')->setParameter('s', Ride::STATUS_COMPLETED)->getQuery()->getSingleScalarResult();
        $revenue   = (clone $qb)->select('SUM(r.price)')->where('r.status = :s')->setParameter('s', Ride::STATUS_COMPLETED)->getQuery()->getSingleScalarResult();

        return [
            'total'     => (int) $total,
            'pending'   => (int) $pending,
            'active'    => (int) $active,
            'completed' => (int) $completed,
            'revenue'   => (float) ($revenue ?? 0),
        ];
    }

    public function getStatsForHotel(Hotel $hotel): array
    {
        $qb = $this->createQueryBuilder('r')->where('r.hotel = :hotel')->setParameter('hotel', $hotel);

        $total      = (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $completed  = (clone $qb)->select('COUNT(r.id)')->andWhere('r.status = :s')->setParameter('s', Ride::STATUS_COMPLETED)->getQuery()->getSingleScalarResult();
        $pending    = (clone $qb)->select('COUNT(r.id)')->andWhere('r.status = :s')->setParameter('s', Ride::STATUS_PENDING)->getQuery()->getSingleScalarResult();
        $commission = (clone $qb)->select('SUM(r.hotelCommission)')->andWhere('r.status = :s')->setParameter('s', Ride::STATUS_COMPLETED)->getQuery()->getSingleScalarResult();

        return [
            'total'      => (int) $total,
            'completed'  => (int) $completed,
            'pending'    => (int) $pending,
            'commission' => (float) ($commission ?? 0),
        ];
    }
}
