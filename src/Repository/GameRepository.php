<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
final class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function findByExternalId(string $externalId): ?Game
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function persist(Game $game): void
    {
        $this->getEntityManager()->persist($game);
    }

    /**
     * Berilgan sana bo'yicha o'yinlar. Kun faqat asl vaqt (match_at, UTC) bo'yicha hisoblanadi.
     * Toshkent vaqti hisobga olinmaydi — 01:00 Toshkent (oldingi kun UTC) "bugun"ga kirmaydi.
     *
     * @return Game[]
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        $d = \DateTimeImmutable::createFromInterface($date);
        $ymd = $d->format('Y-m-d');
        $dayStartUtc = new \DateTimeImmutable($ymd . ' 00:00:00', new \DateTimeZone('UTC'));
        $dayEndUtc = $dayStartUtc->modify('+1 day');

        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.homeClub', 'h')
            ->innerJoin('g.awayClub', 'a')
            ->innerJoin('g.competition', 'c')
            ->where('g.matchAt >= :dayStartUtc AND g.matchAt < :dayEndUtc')
            ->setParameter('dayStartUtc', $dayStartUtc)
            ->setParameter('dayEndUtc', $dayEndUtc)
            ->orderBy('g.matchAt', 'ASC')
            ->addOrderBy('c.nameOriginal', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Jamoa nomi (name_original / name_uz) bo'yicha so'nggi o'yinlar. match_at kamayish.
     *
     * @return Game[]
     */
    public function findLastByTeamName(string $query, int $limit = 10): array
    {
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($query)) . '%';

        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.homeClub', 'h')
            ->innerJoin('g.awayClub', 'a')
            ->innerJoin('g.competition', 'c')
            ->where(
                '(LOWER(h.nameOriginal) LIKE LOWER(:q) OR (h.nameUz IS NOT NULL AND LOWER(h.nameUz) LIKE LOWER(:q))) 
                OR (LOWER(a.nameOriginal) LIKE LOWER(:q) OR (a.nameUz IS NOT NULL AND LOWER(a.nameUz) LIKE LOWER(:q)))'
            )
            ->setParameter('q', $q)
            ->orderBy('g.matchAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Boshlanishiga 1 soat qolgan o'yinlar (O'zbekiston vaqti), reminder yuborilmagan.
     *
     * @return Game[]
     */
    public function findStartingInOneHour(): array
    {
        $tz = new \DateTimeZone('Asia/Tashkent');
        $now = new \DateTimeImmutable('now', $tz);
        $start = $now->modify('+59 minutes');
        $end = $now->modify('+61 minutes');

        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.homeClub', 'h')
            ->innerJoin('g.awayClub', 'a')
            ->innerJoin('g.competition', 'c')
            ->where('g.reminderSentAt IS NULL')
            ->andWhere(
                '(g.matchAtUz IS NOT NULL AND g.matchAtUz >= :start AND g.matchAtUz < :end) 
                OR (g.matchAtUz IS NULL AND g.matchAt >= :startUtc AND g.matchAt < :endUtc)'
            )
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('startUtc', $start->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('endUtc', $end->setTimezone(new \DateTimeZone('UTC')))
            ->orderBy('g.matchAt', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
