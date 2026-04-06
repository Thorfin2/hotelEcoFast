<?php

namespace App\Repository;

use App\Entity\Hotel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HotelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hotel::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.isActive = true')
            ->orderBy('h.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithStats(): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.rides', 'r')
            ->addSelect('COUNT(r.id) as totalRides')
            ->groupBy('h.id')
            ->where('h.isActive = true')
            ->orderBy('h.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
