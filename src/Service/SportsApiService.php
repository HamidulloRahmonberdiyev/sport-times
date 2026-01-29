<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Barcha ma'lumotlar FAQAT Football-Data.org orqali (API key kerak).
 * https://www.football-data.org
 *
 * TOP-5 liga (Premier League, La Liga, Serie A, Bundesliga, Ligue 1) va UCL.
 * Vaqtlar O'zbekiston (Asia/Tashkent) da.
 * .env: FOOTBALL_DATA_ORG_TOKEN=...
 */
final class SportsApiService
{
    private const FD_BASE = 'https://api.football-data.org/v4';
    /**
     * Musobaqa kodlari. Rasmdagi kabi: /v4/competitions/PL/matches — APL o'yinlari.
     * Headers: X-Auth-Token = .env dagi FOOTBALL_DATA_ORG_TOKEN (sizning API kalitingiz).
     * PL=Premier League, PD=La Liga, BL1=Bundesliga, SA=Serie A, FL1=Ligue 1, CL=Champions League.
     */
    private const FD_COMPETITION_CODES = ['PL', 'PD', 'BL1', 'SA', 'FL1', 'CL'];

    /** Faqat shu ligalar chiqadi. */
    public const LEAGUES_TOP5_UCL = [
        'English Premier League', 'Premier League', 'La Liga', 'LaLiga', 'Spanish La Liga',
        'Italian Serie A', 'Serie A', 'German Bundesliga', 'Bundesliga',
        'French Ligue 1', 'Ligue 1', 'UEFA Champions League', 'Champions League',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'FOOTBALL_DATA_ORG_TOKEN')]
        private readonly string $footballDataToken,
    ) {
    }

    /**
     * Berilgan sana oralig'idagi (dateFrom–dateTo) TOP-5 + UCL o'yinlarini qaytaradi.
     * SyncWeekMatches uchun idHomeTeam, idAwayTeam, idCompetition, competitionCode ham bor.
     *
     * @return array<int, array{idEvent: string, idHomeTeam: string, idAwayTeam: string, idCompetition: string, competitionCode: string, strEvent: string, strSport: string, strLeague: string, dateEvent: string, strTime: string, strTimestamp: string|null, strHomeTeam: string, strAwayTeam: string, intHomeScore: mixed, intAwayScore: mixed, strStatus: string, strVenue: string}>
     */
    public function getEventsByDateRange(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo): array
    {
        $events = $this->fetchFromFootballDataOrgDateRange($dateFrom, $dateTo);
        usort($events, static function ($a, $b) {
            $c = strcmp($a['strTime'] ?? '', $b['strTime'] ?? '');
            if ($c !== 0) {
                if (($a['strTime'] ?? '') === '--:--') {
                    return 1;
                }
                if (($b['strTime'] ?? '') === '--:--') {
                    return -1;
                }
                return $c;
            }
            return strcmp($a['strLeague'] ?? '', $b['strLeague'] ?? '');
        });
        return array_values($this->filterEvents($events, null, null, null));
    }

    /**
     * Berilgan sanadagi TOP-5 + UCL o'yinlarini qaytaradi.
     * Manba: FAQAT Football-Data.org (X-Auth-Token kerak).
     * Vaqtlar O'zbekiston (Asia/Tashkent) da.
     *
     * @return array<int, array{idEvent: string, strEvent: string, strSport: string, strLeague: string, dateEvent: string, strTime: string, strTimestamp: string|null, strHomeTeam: string, strAwayTeam: string, intHomeScore: mixed, intAwayScore: mixed, strStatus: string, strVenue: string}>
     */
    public function getEventsByDate(
        \DateTimeInterface $date,
        ?string $sport = null,
        ?string $league = null,
        ?array $allowedLeagues = null,
    ): array {
        $events = $this->fetchFromFootballDataOrg($date);
        usort($events, static function ($a, $b) {
            $c = strcmp($a['strTime'] ?? '', $b['strTime'] ?? '');
            if ($c !== 0) {
                if (($a['strTime'] ?? '') === '--:--') {
                    return 1;
                }
                if (($b['strTime'] ?? '') === '--:--') {
                    return -1;
                }
                return $c;
            }
            return strcmp($a['strLeague'] ?? '', $b['strLeague'] ?? '');
        });
        $events = array_values($events);
        return $this->filterEvents($events, $sport, $league, $allowedLeagues);
    }

    /**
     * Faqat futbol va (agar berilsa) FAQAT TOP-5 + UCL ligalarini qoldiradi.
     * NBA, baseball va boshqa sport/ligalarni olib tashlaydi.
     */
    private function filterEvents(array $events, ?string $sport, ?string $league, ?array $allowedLeagues): array
    {
        $out = [];
        foreach ($events as $e) {
            if ($sport !== null && $sport !== '') {
                $s = mb_strtolower((string) ($e['strSport'] ?? ''));
                if (!\in_array($s, ['soccer', 'football'], true)) {
                    continue;
                }
            }
            if ($allowedLeagues !== null && \count($allowedLeagues) > 0) {
                $l = (string) ($e['strLeague'] ?? '');
                if (!\in_array($l, $allowedLeagues, true)) {
                    continue;
                }
            }
            if ($league !== null && $league !== '') {
                if ((string) ($e['strLeague'] ?? '') !== $league) {
                    continue;
                }
            }
            $out[] = $e;
        }
        return $out;
    }

    private function mapFdStatus(string $s): string
    {
        return match (strtoupper($s)) {
            'FINISHED' => 'FT',
            'TIMED', 'SCHEDULED' => 'NS',
            'IN_PLAY', 'PAUSED' => 'LIVE',
            'POSTPONED', 'SUSPENDED', 'CANCELLED' => 'CANC',
            default => $s ?: 'NS',
        };
    }

    /**
     * FD API utcDate (ISO 8601 UTC, masalan "2025-01-28T14:30:00Z") dan
     * O'zbekiston vaqtida H:i qaytaradi. Vaqt yo'q yoki xato bo'lsa '--:--'.
     */
    private function utcDateToStrTime(string $utcDate): string
    {
        $utcDate = trim($utcDate);
        if ($utcDate === '' || !str_contains($utcDate, 'T')) {
            return '--:--';
        }
        try {
            $dt = new \DateTimeImmutable($utcDate, new \DateTimeZone('UTC'));
            return $dt->setTimezone(new \DateTimeZone('Asia/Tashkent'))->format('H:i');
        } catch (\Throwable) {
            return '--:--';
        }
    }

    /**
     * Football-Data.org: rasmdagi kabi.
     * URL: /v4/competitions/PL/matches (APL), /v4/competitions/PD/matches va h.k.
     * Headers: X-Auth-Token = sizning API kalitingiz (.env: FOOTBALL_DATA_ORG_TOKEN).
     * dateFrom, dateTo orqali sana filtirlanadi. utcDate → O'zbekiston (Asia/Tashkent).
     */
    private function fetchFromFootballDataOrg(\DateTimeInterface $date): array
    {
        if ($this->footballDataToken === '') {
            return [];
        }
        $d = $date->format('Y-m-d');
        $allMatches = [];
        $headers = ['X-Auth-Token' => $this->footballDataToken];

        foreach (self::FD_COMPETITION_CODES as $code) {
            $url = self::FD_BASE . '/competitions/' . $code . '/matches';
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'query' => ['dateFrom' => $d, 'dateTo' => $d],
                    'headers' => $headers,
                ]);
                if ($response->getStatusCode() !== 200) {
                    continue;
                }
                $data = $response->toArray();
                $list = $data['matches'] ?? [];
                if (\is_array($list)) {
                    foreach ($list as $m) {
                        $allMatches[] = $m;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $out = [];
        $matches = $allMatches;

        foreach ($matches as $m) {
            $utcDate = (string) ($m['utcDate'] ?? '');
            $home = $m['homeTeam'] ?? [];
            $away = $m['awayTeam'] ?? [];
            $homeName = (string) ($home['name'] ?? '');
            $awayName = (string) ($away['name'] ?? '');
            $comp = $m['competition'] ?? [];
            $leagueName = (string) ($comp['name'] ?? '');
            $venue = isset($m['venue']) ? (string) $m['venue'] : '';
            $status = $this->mapFdStatus((string) ($m['status'] ?? ''));
            $score = ($m['score'] ?? [])['fullTime'] ?? null;
            $sh = $sa = null;
            if (\is_array($score) && isset($score['home'], $score['away'])) {
                $sh = $score['home'];
                $sa = $score['away'];
            }

            $strTime = $this->utcDateToStrTime($utcDate);

            $dateEvent = $d;
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $utcDate, $x)) {
                $dateEvent = $x[1];
            }

            $out[] = [
                'idEvent' => (string) ($m['id'] ?? ''),
                'strEvent' => $homeName . ' — ' . $awayName,
                'strSport' => 'Soccer',
                'strLeague' => $leagueName,
                'dateEvent' => $dateEvent,
                'strTime' => $strTime,
                'strTimestamp' => $utcDate !== '' ? $utcDate : null,
                'strHomeTeam' => $homeName,
                'strAwayTeam' => $awayName,
                'intHomeScore' => $sh,
                'intAwayScore' => $sa,
                'strStatus' => $status,
                'strVenue' => $venue,
            ];
        }

        return $out;
    }

    /**
     * Bir necha kunlik oralik uchun FD matches. idHomeTeam, idAwayTeam, idCompetition, competitionCode qo'shilgan.
     */
    private function fetchFromFootballDataOrgDateRange(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo): array
    {
        if ($this->footballDataToken === '') {
            return [];
        }
        $dFrom = $dateFrom->format('Y-m-d');
        $dTo = $dateTo->format('Y-m-d');
        $allMatches = [];
        $headers = ['X-Auth-Token' => $this->footballDataToken];

        foreach (self::FD_COMPETITION_CODES as $code) {
            $url = self::FD_BASE . '/competitions/' . $code . '/matches';
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'query' => ['dateFrom' => $dFrom, 'dateTo' => $dTo],
                    'headers' => $headers,
                ]);
                if ($response->getStatusCode() !== 200) {
                    continue;
                }
                $data = $response->toArray();
                $list = $data['matches'] ?? [];
                if (\is_array($list)) {
                    foreach ($list as $m) {
                        $m['_fd_code'] = $code;
                        $allMatches[] = $m;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $out = [];
        foreach ($allMatches as $m) {
            $utcDate = (string) ($m['utcDate'] ?? '');
            $home = $m['homeTeam'] ?? [];
            $away = $m['awayTeam'] ?? [];
            $comp = $m['competition'] ?? [];
            $fdCode = (string) ($m['_fd_code'] ?? $comp['code'] ?? '');
            $homeName = (string) ($home['name'] ?? '');
            $awayName = (string) ($away['name'] ?? '');
            $leagueName = (string) ($comp['name'] ?? '');
            $venue = isset($m['venue']) ? (string) $m['venue'] : '';
            $status = $this->mapFdStatus((string) ($m['status'] ?? ''));
            $score = ($m['score'] ?? [])['fullTime'] ?? null;
            $sh = $sa = null;
            if (\is_array($score) && isset($score['home'], $score['away'])) {
                $sh = $score['home'];
                $sa = $score['away'];
            }

            $strTime = $this->utcDateToStrTime($utcDate);
            $dateEvent = $dFrom;
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $utcDate, $x)) {
                $dateEvent = $x[1];
            }

            $out[] = [
                'idEvent' => (string) ($m['id'] ?? ''),
                'idHomeTeam' => (string) ($home['id'] ?? ''),
                'idAwayTeam' => (string) ($away['id'] ?? ''),
                'idCompetition' => (string) ($comp['id'] ?? ''),
                'competitionCode' => $fdCode,
                'strEvent' => $homeName . ' — ' . $awayName,
                'strSport' => 'Soccer',
                'strLeague' => $leagueName,
                'dateEvent' => $dateEvent,
                'strTime' => $strTime,
                'strTimestamp' => $utcDate !== '' ? $utcDate : null,
                'strHomeTeam' => $homeName,
                'strAwayTeam' => $awayName,
                'intHomeScore' => $sh,
                'intAwayScore' => $sa,
                'strStatus' => $status,
                'strVenue' => $venue,
            ];
        }

        return $out;
    }

    /** O'zbekcha yozuvdan inglizcha jamoa nomiga: Barselona→Barcelona, Manchester Yunayted→Manchester United. */
    private const TEAM_NAME_UZ_TO_EN = [
        'barselona' => 'Barcelona',
        'manchester yunayted' => 'Manchester United',
        'manchester yunaytid' => 'Manchester United',
        'manchester siti' => 'Manchester City',
        'real madrid' => 'Real Madrid',
        'bavariya' => 'Bayern',
        'bayern myunhen' => 'Bayern',
        'inter' => 'Inter',
        'internasionale' => 'Inter',
        'milan' => 'Milan',
        'yuventus' => 'Juventus',
        'atletiko' => 'Atletico',
        'atletiko madrid' => 'Atletico Madrid',
        'liverpul' => 'Liverpool',
        'liverpool' => 'Liverpool',
        'arsenal' => 'Arsenal',
        'chelsi' => 'Chelsea',
        'chelsea' => 'Chelsea',
        'tottenxem' => 'Tottenham',
        'tottenxem xotspur' => 'Tottenham',
        'parij sen jermen' => 'Paris Saint-Germain',
        'psj' => 'Paris Saint-Germain',
        'namyun' => 'Napoli',
        'napoli' => 'Napoli',
        'borussiya' => 'Borussia',
        'barcelona' => 'Barcelona',
        'manchester united' => 'Manchester United',
        'manchester city' => 'Manchester City',
        'real madrid' => 'Real Madrid',
        'bayern' => 'Bayern',
        'juventus' => 'Juventus',
        'atletico' => 'Atletico Madrid',
        'tottenham' => 'Tottenham',
        'paris saint-germain' => 'Paris Saint-Germain',
    ];

    /**
     * Jamoaning so'nggi 10 ta o'yinini qaytaradi. football-data.org orqali.
     * O'zbekcha yozuv qo'llab-quvvatlanadi (Barselona, Manchester Yunayted va h.k.).
     *
     * @return array<int, array{idEvent: string, strEvent: string, strLeague: string, dateEvent: string, strTime: string, strHomeTeam: string, strAwayTeam: string, intHomeScore: mixed, intAwayScore: mixed, strStatus: string}>
     */
    public function getLastMatchesByTeamName(string $teamName, int $limit = 10): array
    {
        if ($this->footballDataToken === '') {
            return [];
        }
        $norm = mb_strtolower(trim($teamName));
        $search = self::TEAM_NAME_UZ_TO_EN[$norm] ?? $teamName;

        $teamId = null;
        $headers = ['X-Auth-Token' => $this->footballDataToken];

        foreach (self::FD_COMPETITION_CODES as $code) {
            $url = self::FD_BASE . '/competitions/' . $code . '/teams';
            try {
                $r = $this->httpClient->request('GET', $url, [
                    'headers' => $headers,
                ]);
                if ($r->getStatusCode() !== 200) {
                    continue;
                }
                $data = $r->toArray();
                $teams = $data['teams'] ?? [];
                foreach ($teams as $t) {
                    $name = (string) ($t['name'] ?? '');
                    if ($name === '' || $t['id'] === null) {
                        continue;
                    }
                    $nameLower = mb_strtolower($name);
                    if ($nameLower === mb_strtolower($search)
                        || str_contains($nameLower, mb_strtolower($search))
                        || str_contains(mb_strtolower($search), $nameLower)
                    ) {
                        $teamId = (int) $t['id'];
                        break 2;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($teamId === null) {
            return [];
        }

        $url = self::FD_BASE . '/teams/' . $teamId . '/matches?limit=' . $limit;
        try {
            $r = $this->httpClient->request('GET', $url, [
                'headers' => ['X-Auth-Token' => $this->footballDataToken],
            ]);
            if ($r->getStatusCode() !== 200) {
                return [];
            }
            $data = $r->toArray();
        } catch (\Throwable) {
            return [];
        }

        $matches = $data['matches'] ?? [];
        if (!\is_array($matches)) {
            return [];
        }

        $out = [];
        foreach ($matches as $m) {
            $utcDate = (string) ($m['utcDate'] ?? '');
            $home = $m['homeTeam'] ?? [];
            $away = $m['awayTeam'] ?? [];
            $homeName = (string) ($home['name'] ?? '');
            $awayName = (string) ($away['name'] ?? '');
            $comp = $m['competition'] ?? [];
            $leagueName = (string) ($comp['name'] ?? '');
            $status = $this->mapFdStatus((string) ($m['status'] ?? ''));
            $score = ($m['score'] ?? [])['fullTime'] ?? null;
            $sh = $sa = null;
            if (\is_array($score) && isset($score['home'], $score['away'])) {
                $sh = $score['home'];
                $sa = $score['away'];
            }

            $strTime = $this->utcDateToStrTime($utcDate);

            $dateEvent = '';
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $utcDate, $x)) {
                $dateEvent = $x[1];
            }

            $out[] = [
                'idEvent' => (string) ($m['id'] ?? ''),
                'strEvent' => $homeName . ' — ' . $awayName,
                'strLeague' => $leagueName,
                'dateEvent' => $dateEvent,
                'strTime' => $strTime,
                'strHomeTeam' => $homeName,
                'strAwayTeam' => $awayName,
                'intHomeScore' => $sh,
                'intAwayScore' => $sa,
                'strStatus' => $status,
            ];
        }

        return $out;
    }
}
