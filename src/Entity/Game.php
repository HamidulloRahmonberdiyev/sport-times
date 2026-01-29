<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bitta o'yin. match_at — asl/UTC vaqt (API dan); match_at_uz — O'zbekiston (Asia/Tashkent) vaqti.
 * Football-Data.org match id — external_id.
 */
#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'game')]
#[ORM\Index(columns: ['match_at'], name: 'idx_game_match_at')]
#[ORM\Index(columns: ['competition_id'], name: 'idx_game_competition_id')]
#[ORM\Index(columns: ['match_at', 'competition_id'], name: 'idx_game_match_at_competition')]
#[ORM\Index(columns: ['status'], name: 'idx_game_status')]
#[ORM\UniqueConstraint(name: 'uq_game_external_id', columns: ['external_id'])]
#[ORM\HasLifecycleCallbacks]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** Football-Data.org match id. */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $externalId;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Club $homeClub;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Club $awayClub;

    #[ORM\ManyToOne(targetEntity: Competition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Competition $competition;

    /** O'yin boshlanish vaqti — asl (UTC, API dan). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $matchAt;

    /** O'yin boshlanish vaqti — O'zbekiston (Asia/Tashkent). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $matchAtUz = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $homeScore = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $awayScore = null;

    /** FT, NS, LIVE, CANC va boshqalar. */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $venue = null;

    /** "1 soat qoldi" bildirishnomasi yuborilgan vaqt. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

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

    public function getHomeClub(): Club
    {
        return $this->homeClub;
    }

    public function setHomeClub(Club $homeClub): self
    {
        $this->homeClub = $homeClub;
        return $this;
    }

    public function getAwayClub(): Club
    {
        return $this->awayClub;
    }

    public function setAwayClub(Club $awayClub): self
    {
        $this->awayClub = $awayClub;
        return $this;
    }

    public function getCompetition(): Competition
    {
        return $this->competition;
    }

    public function setCompetition(Competition $competition): self
    {
        $this->competition = $competition;
        return $this;
    }

    public function getMatchAt(): \DateTimeImmutable
    {
        return $this->matchAt;
    }

    public function setMatchAt(\DateTimeImmutable $matchAt): self
    {
        $this->matchAt = $matchAt;
        return $this;
    }

    public function getMatchAtUz(): ?\DateTimeImmutable
    {
        return $this->matchAtUz;
    }

    public function setMatchAtUz(?\DateTimeImmutable $matchAtUz): self
    {
        $this->matchAtUz = $matchAtUz;
        return $this;
    }

    public function getHomeScore(): ?int
    {
        return $this->homeScore;
    }

    public function setHomeScore(?int $homeScore): self
    {
        $this->homeScore = $homeScore;
        return $this;
    }

    public function getAwayScore(): ?int
    {
        return $this->awayScore;
    }

    public function setAwayScore(?int $awayScore): self
    {
        $this->awayScore = $awayScore;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getVenue(): ?string
    {
        return $this->venue;
    }

    public function setVenue(?string $venue): self
    {
        $this->venue = $venue;
        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): self
    {
        $this->reminderSentAt = $reminderSentAt;
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
