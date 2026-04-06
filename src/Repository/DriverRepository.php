<?php

namespace App\Repository;

use App\Entity\Driver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DriverRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Driver::class);
    }

    public function findAvailable(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = true')
            ->andWhere('d.status = :status')
            ->setParameter('status', Driver::STATUS_AVAILABLE)
            ->orderBy('d.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = true')
            ->orderBy('d.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByUser(int $userId): ?Driver
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
