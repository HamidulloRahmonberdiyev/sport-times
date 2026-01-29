<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TelegramSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TelegramSubscriber>
 */
final class TelegramSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramSubscriber::class);
    }

    public function findActiveChatIds(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.chatId')
            ->where('s.isActive = :on')
            ->setParameter('on', true)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('strval', $rows);
    }

    public function findByChatId(string $chatId): ?TelegramSubscriber
    {
        return $this->findOneBy(['chatId' => $chatId]);
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.isActive = :on')
            ->setParameter('on', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deactivateByChatId(string $chatId): void
    {
        $s = $this->findByChatId($chatId);
        if ($s !== null && $s->isActive()) {
            $s->setIsActive(false);
            $this->getEntityManager()->flush();
        }
    }

    public function persist(TelegramSubscriber $s): void
    {
        $this->getEntityManager()->persist($s);
    }
}
