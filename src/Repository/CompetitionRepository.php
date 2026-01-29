<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Competition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Competition>
 */
final class CompetitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Competition::class);
    }

    public function findByExternalId(string $externalId): ?Competition
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function persist(Competition $competition): void
    {
        $this->getEntityManager()->persist($competition);
    }
}
