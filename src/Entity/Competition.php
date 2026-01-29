<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompetitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Musobaqa (liga / kubok). TOP‑5 (PL, PD, BL1, SA, FL1) va UCL (CL).
 * Football-Data.org dagi competition id — external_id.
 */
#[ORM\Entity(repositoryClass: CompetitionRepository::class)]
#[ORM\Table(name: 'competition')]
#[ORM\Index(columns: ['code'], name: 'idx_competition_code')]
#[ORM\UniqueConstraint(name: 'uq_competition_external_id', columns: ['external_id'])]
#[ORM\HasLifecycleCallbacks]
class Competition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** Football-Data.org competition id. */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $externalId;

    /** Qisqa kod: PL, PD, BL1, SA, FL1, CL. */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $code;

    /** Asl nomi (masalan: "Premier League", "UEFA Champions League"). */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $nameOriginal;

    /** O'zbekcha nomi (kelajakda ishlatish uchun, hozircha null). */
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
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
