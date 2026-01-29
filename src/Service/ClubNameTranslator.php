<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Klubning asl (original) nomidan o'zbekcha nomiga tarjima.
 * Faqat mavjud lug'at bo'yicha; bo'lmasa null qaytadi.
 */
final class ClubNameTranslator
{
    /** Asl nom (lowercase) => o'zbekcha nom. TOP‑5 (PL, La Liga, Serie A, Bundesliga, Ligue 1) + UCL dagi asosiy klublar. */
    private const NAME_ORIGINAL_TO_UZ = [
        // ——— Premier League ———
        'arsenal' => 'Arsenal',
        'aston villa' => 'Aston Villa',
        'bournemouth' => 'Bornmut',
        'brentford' => 'Brentford',
        'brighton' => 'Brayton',
        'brighton & hove albion' => 'Brayton',
        'chelsea' => 'Chelsi',
        'crystal palace' => 'Kristal Peles',
        'everton' => 'Everton',
        'fulham' => 'Fulhem',
        'ipswich town' => 'Ipsvich',
        'ipswich' => 'Ipsvich',
        'leicester city' => 'Lester Siti',
        'leicester' => 'Lester',
        'liverpool' => 'Liverpul',
        'manchester city' => 'Manchester Siti',
        'manchester united' => 'Manchester Yunayted',
        'newcastle united' => 'Nyukasl Yunayted',
        'newcastle' => 'Nyukasl',
        'nottingham forest' => 'Nottingem Forest',
        'southampton' => 'Sautgempton',
        'tottenham hotspur' => 'Tottenxem Xotspur',
        'tottenham' => 'Tottenxem',
        'west ham united' => 'Vest Xem',
        'west ham' => 'Vest Xem',
        'wolverhampton wanderers' => 'Volverxempton',
        'wolverhampton' => 'Volverxempton',
        'wolves' => 'Volves',

        // ——— La Liga ———
        'alavés' => 'Alaves',
        'alaves' => 'Alaves',
        'athletic club' => 'Atletik Bilbao',
        'athletic bilbao' => 'Atletik Bilbao',
        'atletico madrid' => 'Atletiko Madrid',
        'atletico' => 'Atletiko',
        'barcelona' => 'Barselona',
        'cadiz' => 'Kadis',
        'cádiz' => 'Kadis',
        'celta vigo' => 'Selta Vigo',
        'getafe' => 'Xetafe',
        'girona' => 'Jirona',
        'granada' => 'Granada',
        'las palmas' => 'Las Palmas',
        'mallorca' => 'Malyorka',
        'osasuna' => 'Osasuna',
        'rayo vallecano' => 'Rayo Valyekano',
        'real betis' => 'Real Betis',
        'real madrid' => 'Real Madrid',
        'real sociedad' => 'Real Sosyedad',
        'sevilla' => 'Sevilya',
        'valencia' => 'Valensiya',
        'villarreal' => 'Vilyarreal',

        // ——— Serie A ———
        'atalanta' => 'Atalanta',
        'bologna' => 'Bolonya',
        'cagliari' => 'Kalyari',
        'empoli' => 'Empoli',
        'fiorentina' => 'Fiorentina',
        'frosinone' => 'Frozinone',
        'genoa' => 'Jenova',
        'inter' => 'Inter',
        'inter milan' => 'Inter',
        'juventus' => 'Yuventus',
        'lazio' => 'Latsio',
        'lecce' => 'Lechche',
        'ac milan' => 'Milan',
        'milan' => 'Milan',
        'monza' => 'Monza',
        'napoli' => 'Namyun',
        'roma' => 'Roma',
        'salernitana' => 'Salernitana',
        'sassuolo' => 'Sassuolo',
        'torino' => 'Torino',
        'udinese' => 'Udineze',
        'verona' => 'Verona',
        'hellas verona' => 'Verona',

        // ——— Bundesliga ———
        'augsburg' => 'Augsburg',
        'bayer leverkusen' => 'Bayer Leverkuzen',
        'leverkusen' => 'Leverkuzen',
        'bayern munich' => 'Bavariya (Myunhen)',
        'bayern' => 'Bavariya',
        'bochum' => 'Boxum',
        'borussia dortmund' => 'Borussiya Dortmund',
        'dortmund' => 'Dortmund',
        'borussia mönchengladbach' => 'Borussiya Myonxengladbax',
        'borussia moenchengladbach' => 'Borussiya Myonxengladbax',
        'mönchengladbach' => 'Myonxengladbax',
        'darmstadt' => 'Darmstadt',
        'eintracht frankfurt' => 'Ayntraxt Frankfurt',
        'frankfurt' => 'Frankfurt',
        'freiburg' => 'Frayburg',
        'sc freiburg' => 'Frayburg',
        'heidenheim' => 'Xaydenxaym',
        'hoffenheim' => 'Xofenxaym',
        'tsg hoffenheim' => 'Xofenxaym',
        '1. fc köln' => 'Köln',
        'fc köln' => 'Köln',
        'köln' => 'Köln',
        'koeln' => 'Köln',
        'rb leipzig' => 'RB Layptsig',
        'leipzig' => 'Layptsig',
        'mainz' => 'Mayns',
        '1. fsg mainz' => 'Mayns',
        'mainz 05' => 'Mayns',
        'union berlin' => 'Union Berlin',
        'werder bremen' => 'Verder Bremen',
        'bremen' => 'Bremen',
        'wolfsburg' => 'Volfsburg',

        // ——— Ligue 1 ———
        'brest' => 'Brest',
        'clermont' => 'Klermon',
        'clermont foot' => 'Klermon',
        'le havre' => 'Le Havr',
        'havre' => 'Le Havr',
        'lens' => 'Lens',
        'lille' => 'Lil',
        'lorient' => 'Loryan',
        'lyon' => 'Lyon',
        'olympique lyonnais' => 'Lyon',
        'marseille' => 'Marsel',
        'olympique marseille' => 'Marsel',
        'olympique de marseille' => 'Marsel',
        'metz' => 'Mets',
        'monaco' => 'Monako',
        'as monaco' => 'Monako',
        'montpellier' => 'Monpelye',
        'nantes' => 'Nant',
        'nice' => 'Nitsa',
        'ogc nice' => 'Nitsa',
        'paris saint-germain' => 'Parij Sen Jermen',
        'psg' => 'PSJ',
        'reims' => 'Reyms',
        'stade de reims' => 'Reyms',
        'rennes' => 'Renn',
        'stade rennais' => 'Renn',
        'strasbourg' => 'Strasburg',
        'rc strasbourg' => 'Strasburg',
        'toulouse' => 'Tuluza',
    ];

    public function toUz(string $nameOriginal): ?string
    {
        if ($nameOriginal === '') {
            return null;
        }
        $key = mb_strtolower(trim($nameOriginal));
        return self::NAME_ORIGINAL_TO_UZ[$key] ?? $this->findByContains($key);
    }

    /** Qisman moslik: "Manchester United FC" -> "Manchester Yunayted" kabi. */
    private function findByContains(string $key): ?string
    {
        foreach (array_keys(self::NAME_ORIGINAL_TO_UZ) as $orig) {
            if (str_contains($key, $orig) || str_contains($orig, $key)) {
                return self::NAME_ORIGINAL_TO_UZ[$orig];
            }
        }
        return null;
    }
}
