<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\Competition;
use App\Entity\Game;
use App\Repository\ClubRepository;
use App\Repository\CompetitionRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bir haftalik (7 kun) TOP‑5 + UCL o'yinlarini Football-Data.org dan yuklab,
 * club, competition, match jadvallariga create/update qiladi.
 */
final class SyncWeekMatchesService
{
    /** Bir sync davrida DB ga yozilmagan Competition/Club ni takrorlashdan saqlash. */
    private array $competitionCache = [];
    private array $clubCache = [];

    public function __construct(
        private readonly SportsApiService $sportsApi,
        private readonly ClubNameTranslator $clubNameTranslator,
        private readonly ClubRepository $clubRepo,
        private readonly CompetitionRepository $competitionRepo,
        private readonly GameRepository $gameRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Berilgan kundan boshlab 7 kunlik o'yinlarni sinxronlaydi.
     * $from null bo'lsa bugungi sana (Asia/Tashkent).
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function syncWeek(?\DateTimeInterface $from = null): array
    {
        $tz = new \DateTimeZone('Asia/Tashkent');
        $from = $from ? \DateTimeImmutable::createFromInterface($from)->setTimezone($tz) : new \DateTimeImmutable('today', $tz);
        $to = $from->modify('+6 days');

        $events = $this->sportsApi->getEventsByDateRange($from, $to);

        $this->competitionCache = [];
        $this->clubCache = [];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($events as $e) {
            $idHome = (string) ($e['idHomeTeam'] ?? '');
            $idAway = (string) ($e['idAwayTeam'] ?? '');
            $idComp = (string) ($e['idCompetition'] ?? '');
            $idEvent = (string) ($e['idEvent'] ?? '');

            if ($idEvent === '') {
                $skipped++;
                continue;
            }
            if ($idHome === '' || $idAway === '') {
                $errors[] = "idEvent={$idEvent}: idHomeTeam yoki idAwayTeam bo'sh, o'tkazib yuborildi.";
                $skipped++;
                continue;
            }

            $competition = $this->getOrCreateCompetition(
                $idComp,
                (string) ($e['competitionCode'] ?? ''),
                (string) ($e['strLeague'] ?? '')
            );
            $homeClub = $this->getOrCreateClub($idHome, (string) ($e['strHomeTeam'] ?? ''));
            $awayClub = $this->getOrCreateClub($idAway, (string) ($e['strAwayTeam'] ?? ''));

            if ($homeClub === null || $awayClub === null) {
                $errors[] = "idEvent={$idEvent}: Club yaratib bo'lmadi.";
                $skipped++;
                continue;
            }

            $matchAt = $this->parseMatchAt(
                (string) ($e['strTimestamp'] ?? ''),
                (string) ($e['dateEvent'] ?? ''),
                (string) ($e['strTime'] ?? '')
            );

            if ($matchAt === null) {
                $errors[] = "idEvent={$idEvent}: match_at aniqlanmadi (utcDate yo'q yoki xato).";
                $skipped++;
                continue;
            }

            $rangeStart = $from->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'));
            $rangeEnd = $to->modify('+1 day')->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'));
            if ($matchAt < $rangeStart || $matchAt >= $rangeEnd) {
                $errors[] = "idEvent={$idEvent}: match_at hafta oralig'ida emas, o'tkazib yuborildi.";
                $skipped++;
                continue;
            }

            $matchAtUz = $matchAt->setTimezone(new \DateTimeZone('Asia/Tashkent'));

            $existing = $this->gameRepo->findByExternalId($idEvent);
            if ($existing !== null) {
                $existing->setHomeClub($homeClub);
                $existing->setAwayClub($awayClub);
                $existing->setCompetition($competition);
                $existing->setMatchAt($matchAt);
                $existing->setMatchAtUz($matchAtUz);
                $existing->setHomeScore(self::intOrNull($e['intHomeScore'] ?? null));
                $existing->setAwayScore(self::intOrNull($e['intAwayScore'] ?? null));
                $existing->setStatus((string) ($e['strStatus'] ?? 'NS'));
                $existing->setVenue(self::strOrNull($e['strVenue'] ?? null));
                $this->gameRepo->persist($existing);
                $updated++;
            } else {
                $game = new Game();
                $game->setExternalId($idEvent);
                $game->setHomeClub($homeClub);
                $game->setAwayClub($awayClub);
                $game->setCompetition($competition);
                $game->setMatchAt($matchAt);
                $game->setMatchAtUz($matchAtUz);
                $game->setHomeScore(self::intOrNull($e['intHomeScore'] ?? null));
                $game->setAwayScore(self::intOrNull($e['intAwayScore'] ?? null));
                $game->setStatus((string) ($e['strStatus'] ?? 'NS'));
                $game->setVenue(self::strOrNull($e['strVenue'] ?? null));
                $this->gameRepo->persist($game);
                $created++;
            }
        }

        try {
            $this->em->flush();
        } catch (\Throwable $t) {
            $errors[] = 'Flush xatosi: ' . $t->getMessage();
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function getOrCreateCompetition(string $externalId, string $code, string $nameOriginal): Competition
    {
        if ($externalId === '') {
            $externalId = 'code_' . ($code !== '' ? $code : 'unknown');
        }
        if (isset($this->competitionCache[$externalId])) {
            $c = $this->competitionCache[$externalId];
            if ($c->getCode() !== $code) {
                $c->setCode($code);
            }
            if ($c->getNameOriginal() !== $nameOriginal) {
                $c->setNameOriginal($nameOriginal);
            }
            return $c;
        }
        $c = $this->competitionRepo->findByExternalId($externalId);
        if ($c !== null) {
            $this->competitionCache[$externalId] = $c;
            if ($c->getCode() !== $code) {
                $c->setCode($code);
            }
            if ($c->getNameOriginal() !== $nameOriginal) {
                $c->setNameOriginal($nameOriginal);
            }
            return $c;
        }
        $c = new Competition();
        $c->setExternalId($externalId);
        $c->setCode($code);
        $c->setNameOriginal($nameOriginal);
        $this->competitionRepo->persist($c);
        $this->competitionCache[$externalId] = $c;
        return $c;
    }

    private function getOrCreateClub(string $externalId, string $nameOriginal): ?Club
    {
        if ($externalId === '') {
            return null;
        }
        if (isset($this->clubCache[$externalId])) {
            $c = $this->clubCache[$externalId];
            if ($c->getNameOriginal() !== $nameOriginal) {
                $c->setNameOriginal($nameOriginal);
            }
            $uz = $this->clubNameTranslator->toUz($nameOriginal);
            if ($uz !== null && $c->getNameUz() !== $uz) {
                $c->setNameUz($uz);
            }
            return $c;
        }
        $c = $this->clubRepo->findByExternalId($externalId);
        if ($c !== null) {
            $this->clubCache[$externalId] = $c;
            if ($c->getNameOriginal() !== $nameOriginal) {
                $c->setNameOriginal($nameOriginal);
            }
            $uz = $this->clubNameTranslator->toUz($nameOriginal);
            if ($uz !== null && $c->getNameUz() !== $uz) {
                $c->setNameUz($uz);
            }
            return $c;
        }
        $c = new Club();
        $c->setExternalId($externalId);
        $c->setNameOriginal($nameOriginal);
        $c->setNameUz($this->clubNameTranslator->toUz($nameOriginal));
        $this->clubRepo->persist($c);
        $this->clubCache[$externalId] = $c;
        return $c;
    }

    /**
     * Faqat utcDate (strTimestamp) dan vaqt olinadi. dateEvent/strTime fallback ishlatilmaydi —
     * ular matchday/round sanasi bo'lib, haqiqiy o'yin sanasidan farq qilishi mumkin.
     */
    private function parseMatchAt(string $strTimestamp, string $dateEvent, string $strTime): ?\DateTimeImmutable
    {
        $strTimestamp = trim($strTimestamp);
        if ($strTimestamp === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($strTimestamp, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (int) $v;
    }

    private static function strOrNull(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}
