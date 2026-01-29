<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DailyBroadcastLog;
use App\Entity\Game;
use App\Repository\DailyBroadcastLogRepository;
use App\Repository\GameRepository;
use App\Repository\TelegramSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Har kuni 9:00 da bugungi o'yinlar va o'yindan 1 soat oldin bildirishnoma yuborish.
 * Ma'lumotlar bazadan.
 */
final class TelegramNotificationService
{
    private const TZ_TASHKENT = 'Asia/Tashkent';
    private const DAILY_HOUR = 9;
    private const DELAY_BETWEEN_USERS_MS = 300;
    private const DELAY_BETWEEN_MATCHES_SEC = 2;

    public function __construct(
        private readonly GameRepository $gameRepo,
        private readonly TelegramSubscriberRepository $subscriberRepo,
        private readonly DailyBroadcastLogRepository $dailyLogRepo,
        private readonly TelegramBotService $telegram,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * 9:00 (Toshkent) da bugungi o'yinlar ro'yxatini obunachilarga yuboradi.
     * O'yinlar bo'lmasa yoki allaqachon yuborilgan bo'lsa hech narsa qilmaydi.
     *
     * $force true bo'lsa: soat 9 ga qaramay yuboradi (qo'lda run uchun).
     */
    public function sendDailyMatchesIfNeeded(bool $force = false): void
    {
        $tz = new \DateTimeZone(self::TZ_TASHKENT);
        $now = new \DateTimeImmutable('now', $tz);
        if (!$force && (int) $now->format('G') !== self::DAILY_HOUR) {
            return;
        }

        $today = $now->setTime(0, 0, 0);
        if ($this->dailyLogRepo->existsForDate($today)) {
            return;
        }

        $games = $this->gameRepo->findByDate($today);
        if (\count($games) === 0) {
            return;
        }

        $text = $this->telegram->formatEventsForDate($today);
        $chatIds = $this->subscriberRepo->findActiveChatIds();
        foreach ($chatIds as $chatId) {
            try {
                $this->telegram->sendMessage((int) $chatId, $text);
            } catch (\Throwable) {
                // skip failed user
            }
            usleep(self::DELAY_BETWEEN_USERS_MS * 1000);
        }

        $log = new DailyBroadcastLog();
        $log->setBroadcastDate($today);
        $log->setSentAt(new \DateTimeImmutable('now', $tz));
        $this->dailyLogRepo->persist($log);
        $this->em->flush();
    }

    /**
     * Boshlanishiga 1 soat qolgan o'yinlar uchun "1 soat qoldi" bildirishnomasini yuboradi.
     * Har bir uchrashuv alohida, orasida qisqa pauza.
     */
    public function sendMatchReminders(): void
    {
        $games = $this->gameRepo->findStartingInOneHour();
        $tz = new \DateTimeZone(self::TZ_TASHKENT);
        $chatIds = $this->subscriberRepo->findActiveChatIds();

        foreach ($games as $i => $g) {
            if ($i > 0) {
                sleep(self::DELAY_BETWEEN_MATCHES_SEC);
            }

            $msg = $this->formatReminderMessage($g, $tz);
            foreach ($chatIds as $chatId) {
                try {
                    $this->telegram->sendMessage((int) $chatId, $msg);
                } catch (\Throwable) {
                    // skip
                }
                usleep(self::DELAY_BETWEEN_USERS_MS * 1000);
            }

            $g->setReminderSentAt(new \DateTimeImmutable('now', $tz));
            $this->gameRepo->persist($g);
            $this->em->flush();
        }
    }

    private function formatReminderMessage(Game $g, \DateTimeZone $tz): string
    {
        $home = $g->getHomeClub()->getDisplayName();
        $away = $g->getAwayClub()->getDisplayName();
        $league = $g->getCompetition()->getNameOriginal();
        $time = $g->getMatchAtUz() !== null
            ? $g->getMatchAtUz()->format('H:i')
            : $g->getMatchAt()->setTimezone($tz)->format('H:i');

        return "⏰ <b>Uchrashuv boshlanishiga 1 soat qoldi</b>\n\n"
            . "▸ {$home} — {$away}\n"
            . "🕐 <code>{$time}</code> · {$league}";
    }
}
