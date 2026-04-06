<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Ride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findByRide(Ride $ride): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.ride = :ride')
            ->setParameter('ride', $ride)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
