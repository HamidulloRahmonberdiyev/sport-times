<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClubRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Futbol klubi. Nomi asl (original) va ixtiyoriy o'zbekcha tarjimada saqlanadi.
 * Football-Data.org dagi team id — external_id.
 */
#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[ORM\Table(name: 'club')]
#[ORM\Index(columns: ['name_original'], name: 'idx_club_name_original')]
#[ORM\UniqueConstraint(name: 'uq_club_external_id', columns: ['external_id'])]
#[ORM\HasLifecycleCallbacks]
class Club
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** Football-Data.org team id. */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $externalId;

    /** Klubning asl nomi (masalan: "Barcelona", "Manchester United"). */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $nameOriginal;

    /** O'zbekcha nomi (agar mavjud bo'lsa). Masalan: "Barselona", "Manchester Yunayted". */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $nameUz = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getNameOriginal(): string
    {
        return $this->nameOriginal;
    }

    public function setNameOriginal(string $nameOriginal): self
    {
        $this->nameOriginal = $nameOriginal;
        return $this;
    }

    public function getNameUz(): ?string
    {
        return $this->nameUz;
    }

    public function setNameUz(?string $nameUz): self
    {
        $this->nameUz = $nameUz;
        return $this;
    }

    /** Ko‘rsatish uchun: o‘zbekcha bo‘lsa o‘zbekcha, aks holda asl. */
    public function getDisplayName(): string
    {
        return $this->nameUz ?? $this->nameOriginal;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
