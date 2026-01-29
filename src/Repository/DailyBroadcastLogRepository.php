<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DailyBroadcastLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyBroadcastLog>
 */
final class DailyBroadcastLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyBroadcastLog::class);
    }

    public function existsForDate(\DateTimeInterface $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date->format('Y-m-d'));

        $n = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.broadcastDate = :d')
            ->setParameter('d', $d)
            ->getQuery()
            ->getSingleScalarResult();

        return $n > 0;
    }

    public function persist(DailyBroadcastLog $l): void
    {
        $this->getEntityManager()->persist($l);
    }
}
