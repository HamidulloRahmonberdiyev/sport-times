<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TelegramSubscriber;
use App\Repository\GameRepository;
use App\Repository\TelegramSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class TelegramBotService
{
    private const TELEGRAM_API = 'https://api.telegram.org/bot';

    /** Telegram xatolari: bot bloklangan / chat topilmadi / foydalanuvchi o‘chirilgan. */
    private const BLOCKED_PATTERNS = [
        'blocked by the user',
        'bot was blocked by the user',
        'user is deactivated',
        'chat not found',
        'user not found',
        'bot can\'t initiate conversation',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GameRepository $gameRepo,
        private readonly TelegramSubscriberRepository $subscriberRepo,
        private readonly EntityManagerInterface $em,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'TELEGRAM_BOT_TOKEN')]
        private readonly string $botToken,
    ) {
    }

    /**
     * Webhook URL ni Telegram API ga o'rnatadi. Foydalanuvchi xabarlari shu URL ga POST qilinadi.
     */
    public function setWebhook(string $webhookUrl): void
    {
        $url = self::TELEGRAM_API . $this->botToken . '/setWebhook';
        $this->httpClient->request('POST', $url, [
            'json' => ['url' => $webhookUrl],
        ]);
    }

    /**
     * Webhookni o'chiradi (long polling ga o'tish yoki webhook ni bekor qilish uchun).
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): void
    {
        $url = self::TELEGRAM_API . $this->botToken . '/deleteWebhook';
        $this->httpClient->request('POST', $url, [
            'json' => array_filter(['drop_pending_updates' => $dropPendingUpdates]),
        ]);
    }

    /**
     * Telegram da o'rnatilgan webhook URL va holatini qaytaradi (tekshirish uchun).
     *
     * @return array{url: string, has_custom_certificate: bool, pending_update_count: int}
     */
    public function getWebhookInfo(): array
    {
        $url = self::TELEGRAM_API . $this->botToken . '/getWebhookInfo';
        $response = $this->httpClient->request('GET', $url);
        $data = $response->toArray();
        return [
            'url' => (string) ($data['result']['url'] ?? ''),
            'has_custom_certificate' => (bool) ($data['result']['has_custom_certificate'] ?? false),
            'pending_update_count' => (int) ($data['result']['pending_update_count'] ?? 0),
        ];
    }

    /**
     * Yangi xabarlarni long polling orqali olish.
     *
     * @return array<int, array{update_id: int, message?: array{chat: array{id: int}, text?: string}}>
     */
    public function getUpdates(int $offset = 0, int $timeout = 25): array
    {
        $url = self::TELEGRAM_API . $this->botToken . '/getUpdates';
        $response = $this->httpClient->request('GET', $url, [
            'query' => [
                'offset' => $offset,
                'timeout' => $timeout,
            ],
        ]);

        $data = $response->toArray();
        return $data['result'] ?? [];
    }

    public function sendMessage(int $chatId, string $text, bool $disableNotification = false, ?array $replyMarkup = null): void
    {
        $this->sendMessageOrDeactivate($chatId, $text, $disableNotification, $replyMarkup);
    }

    /**
     * Xabar yuboradi. Blok / o‘chirilgan bo‘lsa obunachini is_active=false qiladi.
     *
     * @return bool True yuborildi, false blok/o‘chirilgan → deactivate qilindi.
     */
    public function sendMessageOrDeactivate(int $chatId, string $text, bool $disableNotification = false, ?array $replyMarkup = null): bool
    {
        $url = self::TELEGRAM_API . $this->botToken . '/sendMessage';
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'disable_notification' => $disableNotification,
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        try {
            $this->httpClient->request('POST', $url, ['json' => $payload]);
            return true;
        } catch (\Throwable $e) {
            if ($this->isBlockedOrGoneError($e)) {
                $this->subscriberRepo->deactivateByChatId((string) $chatId);
                return false;
            }
            throw $e;
        }
    }

    public function getActiveSubscriberCount(): int
    {
        return $this->subscriberRepo->countActive();
    }

    private function isBlockedOrGoneError(\Throwable $e): bool
    {
        if (!$e instanceof ClientExceptionInterface) {
            return false;
        }
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;
        if ($response === null) {
            return false;
        }
        $body = $response->getContent(false);
        $data = json_decode($body, true);
        if (!\is_array($data)) {
            return false;
        }
        $code = (int) ($data['error_code'] ?? 0);
        $desc = mb_strtolower((string) ($data['description'] ?? ''));
        if ($code !== 400 && $code !== 403) {
            return false;
        }
        foreach (self::BLOCKED_PATTERNS as $p) {
            if (str_contains($desc, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Xabarni tahlil qilib, javob matnini generatsiya qiladi.
     * Qaytaredi: [javob matni, yangi offset (process qilingan) yoki null].
     */
    public function processUpdate(array $update): ?array
    {
        $updateId = (int) ($update['update_id'] ?? 0);
        $message = $update['message'] ?? null;
        if (!$message) {
            return [$updateId, null];
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === 0) {
            return [$updateId, null];
        }

        if (str_starts_with($text, '/start') || $text === '/start') {
            $this->registerSubscriber($chatId);
        }

        $reply = $this->buildReply($text);
        $out = ['chat_id' => $chatId, 'text' => $reply['text']];
        if (isset($reply['reply_markup'])) {
            $out['reply_markup'] = $reply['reply_markup'];
        }
        return [$updateId, $out];
    }

    /** Tugmalar: Bugun | Ertaga, keyin ertagadan keyingi 5 kun (2+3+2). */
    public function getMainKeyboard(): array
    {
        $uzMonths = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Tashkent'));
        $rows = [
            [['text' => '📅 Bugun'], ['text' => '📅 Ertaga']],
        ];
        $dates = [];
        for ($i = 2; $i <= 6; $i++) {
            $d = $today->modify("+{$i} days");
            $dates[] = ['text' => $d->format('j') . '-' . $uzMonths[(int) $d->format('n') - 1]];
        }
        $rows[] = [$dates[0], $dates[1], $dates[2]];
        $rows[] = [$dates[3], $dates[4]];
        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * @return array{text: string, reply_markup?: array}
     */
    private function buildReply(string $text): array
    {
        $lower = mb_strtolower($text);
        $kb = $this->getMainKeyboard();

        // /start — xush kelibsiz + bugungi o'yinlar + tugmalar
        if (str_starts_with($text, '/start') || $text === '/start') {
            return [
                'text' => $this->getWelcomeMessage() . "\n\n" . $this->fetchAndFormatEvents(new \DateTimeImmutable('today', new \DateTimeZone('Asia/Tashkent'))),
                'reply_markup' => $kb,
            ];
        }

        // Bugungi o'yinlar (shu jumladan 📅 Bugun)
        if (\in_array($lower, ["bugungi o'yinlar", 'bugungi oyinlar', "bugungi o'yin", 'bugun', '📅 bugun', '/today', ''], true) || $text === '/today' || $text === '📅 Bugun') {
            if ($text === '') {
                return ['text' => $this->getWelcomeMessage(), 'reply_markup' => $kb];
            }
            return ['text' => $this->fetchAndFormatEvents(new \DateTimeImmutable('today', new \DateTimeZone('Asia/Tashkent'))), 'reply_markup' => $kb];
        }

        // Ertangi o'yinlar (shu jumladan 📅 Ertaga)
        if (\in_array($lower, ["ertangi o'yinlar", 'ertangi oyinlar', "ertangi o'yin", 'ertangi', '📅 ertaga', 'ertaga'], true) || $text === '📅 Ertaga') {
            return ['text' => $this->fetchAndFormatEvents(new \DateTimeImmutable('tomorrow', new \DateTimeZone('Asia/Tashkent'))), 'reply_markup' => $kb];
        }

        // /stats — faqat obunachilar soni
        if ($lower === '/stats' || $text === '/stats') {
            $n = $this->getActiveSubscriberCount();
            return ['text' => "👥 <b>Jami obunachilar:</b> {$n} ta.", 'reply_markup' => $kb];
        }

        // Sana: YYYY-MM-DD, DD.MM.YYYY, d-oy (30-yanvar, 1-fevral)
        $date = $this->parseDate($text);
        if (null !== $date) {
            return ['text' => $this->fetchAndFormatEvents($date), 'reply_markup' => $kb];
        }

        // Jamoa nomi — so'nggi 10 o'yin (bazadan; name_original / name_uz)
        if (mb_strlen($text) >= 2) {
            $games = $this->gameRepo->findLastByTeamName($text, 10);
            if (\count($games) > 0) {
                return ['text' => $this->formatTeamMatches($games, $text), 'reply_markup' => $kb];
            }
            return ['text' => "❌ <b>«" . str_replace(['<', '>'], '', $text) . "»</b> boʻyicha jamoa topilmadi. Barselona, Liverpool, Manchester Yunayted kabi yozing.", 'reply_markup' => $kb];
        }

        return ['text' => $this->getHelpMessage(), 'reply_markup' => $kb];
    }

    private function getWelcomeMessage(): string
    {
        return "🏟 <b>TOP-5 LIGA + UCL</b>\n\n"
            . "Vaqtlar: <b>O'zbekiston vaqti</b> (Toshkent)\n\n"
            . "• <b>📅 Bugun</b> / <b>📅 Ertaga</b> yoki sanani tanlang\n"
            . "• <b>Komanda nomi</b> — so'nggi 10 o'yin (Barselona, Liverpool)";
    }

    private function getHelpMessage(): string
    {
        return "Tugmalardan bosing yoki yozing:\n"
            . "• <b>📅 Bugun</b>, <b>📅 Ertaga</b>\n"
            . "• Sana: <code>30-yanvar</code>, <code>1-fevral</code>\n"
            . "• <b>Komanda</b>: Barselona, Liverpool — so'nggi 10 o'yin\n"
            . "• <b>/stats</b> — obunachilar soni";
    }

    public function registerSubscriber(int $chatId): void
    {
        $s = $this->subscriberRepo->findByChatId((string) $chatId);
        if ($s !== null) {
            $s->setIsActive(true);
            $this->subscriberRepo->persist($s);
        } else {
            $s = new TelegramSubscriber();
            $s->setChatId((string) $chatId);
            $this->subscriberRepo->persist($s);
        }
        $this->em->flush();
    }

    private function parseDate(string $text): ?\DateTimeImmutable
    {
        $t = trim($text);
        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $f) {
            $d = \DateTimeImmutable::createFromFormat($f, $t);
            if (false !== $d) {
                return $d;
            }
        }
        // O'zbekcha: 30-yanvar, 1-fevral (yil joriy)
        $uzMonths = ['yanvar' => 1, 'fevral' => 2, 'mart' => 3, 'aprel' => 4, 'may' => 5, 'iyun' => 6, 'iyul' => 7, 'avgust' => 8, 'sentabr' => 9, 'oktabr' => 10, 'noyabr' => 11, 'dekabr' => 12];
        if (preg_match('/^(\d{1,2})\s*-\s*([a-z]+)$/ui', $t, $m)) {
            $day = (int) $m[1];
            $monthName = mb_strtolower($m[2]);
            if (isset($uzMonths[$monthName]) && $day >= 1 && $day <= 31) {
                $year = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tashkent')))->format('Y');
                $d = \DateTimeImmutable::createFromFormat('Y-n-j', $year . '-' . $uzMonths[$monthName] . '-' . $day);
                if (false !== $d) {
                    return $d;
                }
            }
        }
        return null;
    }

    /**
     * @param \App\Entity\Game[] $games
     */
    private function formatTeamMatches(array $games, string $teamQuery): string
    {
        $q = str_replace(['<', '>'], '', $teamQuery);
        $tz = new \DateTimeZone('Asia/Tashkent');
        $lines = [
            "━━━━━━━━━━━━━━━━━━━━",
            "⚽ <b>SO'NGGI 10 O'YIN</b>",
            "▸ <i>{$q}</i>",
            "⏰ O'zbekiston vaqti",
            "━━━━━━━━━━━━━━━━━━━━",
            "",
        ];
        foreach ($games as $g) {
            $time = $this->formatTime(
                $g->getMatchAtUz()?->format('H:i') ?? $g->getMatchAt()->setTimezone($tz)->format('H:i')
            );
            $sh = $g->getHomeScore();
            $sa = $g->getAwayScore();
            $score = (null !== $sh && null !== $sa) ? " <b>({$sh}:{$sa})</b> " : " — ";
            $status = $this->formatStatus($g->getStatus());
            $dt = $g->getMatchAtUz() ?? $g->getMatchAt()->setTimezone($tz);
            $dateStr = $dt->format('d.m.Y');
            $league = $g->getCompetition()->getNameOriginal() !== '' ? " • {$g->getCompetition()->getNameOriginal()}" : '';
            $home = $g->getHomeClub()->getDisplayName();
            $away = $g->getAwayClub()->getDisplayName();
            $lines[] = "▸ <code>{$dateStr}</code> <code>{$time}</code>  {$home} — {$away}{$score}{$status}{$league}";
        }
        $lines[] = "";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        $out = implode("\n", $lines);
        if (mb_strlen($out) > 4000) {
            $out = mb_substr($out, 0, 3997) . '…';
        }
        return $out;
    }

    public function formatEventsForDate(\DateTimeInterface $date): string
    {
        return $this->fetchAndFormatEvents($date);
    }

    private function fetchAndFormatEvents(\DateTimeInterface $date): string
    {
        $games = $this->gameRepo->findByDate($date);

        $uzMonths = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];
        $dateStr = $date->format('j') . '-' . $uzMonths[(int) $date->format('n') - 1] . ' ' . $date->format('Y');
        if (0 === \count($games)) {
            return "━━━━━━━━━━━━━━━━━━━━\n"
                . "🏟 <b>TOP-5 LIGA + UCL</b>\n"
                . "📅 {$dateStr}\n"
                . "⏰ O'zbekiston vaqti\n"
                . "━━━━━━━━━━━━━━━━━━━━\n\n"
                . "Bu sanada o'yinlar topilmadi.";
        }

        $byLeague = [];
        $tz = new \DateTimeZone('Asia/Tashkent');
        foreach ($games as $g) {
            $ln = $g->getCompetition()->getNameOriginal() ?: '—';
            $byLeague[$ln][] = $g;
        }

        $lines = [
            "━━━━━━━━━━━━━━━━━━━━",
            "🏟 <b>TOP-5 LIGA + UCL</b>",
            "📅 {$dateStr}",
            "⏰ O'zbekiston vaqti (Toshkent)",
            "━━━━━━━━━━━━━━━━━━━━",
            "",
        ];
        foreach ($byLeague as $leagueName => $leagueGames) {
            $lines[] = "▸ <b>{$leagueName}</b>";
            $lines[] = "──────────────────";
            foreach ($leagueGames as $g) {
                $time = $this->formatTime(
                    $g->getMatchAtUz()?->format('H:i') ?? $g->getMatchAt()->setTimezone($tz)->format('H:i')
                );
                $sh = $g->getHomeScore();
                $sa = $g->getAwayScore();
                $score = (null !== $sh && null !== $sa) ? "  <b>({$sh}:{$sa})</b> " : "  — ";
                $status = $this->formatStatus($g->getStatus());
                $home = $g->getHomeClub()->getDisplayName();
                $away = $g->getAwayClub()->getDisplayName();
                $lines[] = "  🕐 <code>{$time}</code>  {$home} — {$away}  {$status}";
            }
            $lines[] = "";
        }
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";

        $out = implode("\n", $lines);
        if (mb_strlen($out) > 4000) {
            $out = mb_substr($out, 0, 3997) . '…';
        }
        return $out;
    }

    private function formatTime(string $strTime): string
    {
        if ('' === $strTime) {
            return '--:--';
        }
        $parts = explode(':', $strTime);
        return sprintf('%s:%s', $parts[0] ?? '00', $parts[1] ?? '00');
    }

    private function formatStatus(string $s): string
    {
        return match (mb_strtoupper($s)) {
            'FT' => '✅',
            'NS', 'TBD' => '',
            'LIVE', '1H', '2H', 'HT' => '🔴',
            'PST', 'CANC' => '🚫',
            default => $s ? " ({$s})" : '',
        };
    }
}
