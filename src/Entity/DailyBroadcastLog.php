<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DailyBroadcastLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Har kuni 9:00 da bugungi o'yinlar yuborilganligini qayd qilish. */
#[ORM\Entity(repositoryClass: DailyBroadcastLogRepository::class)]
#[ORM\Table(name: 'daily_broadcast_log')]
#[ORM\UniqueConstraint(name: 'uq_daily_broadcast_log_date', columns: ['broadcast_date'])]
class DailyBroadcastLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    private \DateTimeImmutable $broadcastDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBroadcastDate(): \DateTimeImmutable
    {
        return $this->broadcastDate;
    }

    public function setBroadcastDate(\DateTimeImmutable $broadcastDate): self
    {
        $this->broadcastDate = $broadcastDate;
        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }
}
