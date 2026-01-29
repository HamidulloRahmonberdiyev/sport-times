<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Club>
 */
final class ClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    public function findByExternalId(string $externalId): ?Club
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function persist(Club $club): void
    {
        $this->getEntityManager()->persist($club);
    }
}
