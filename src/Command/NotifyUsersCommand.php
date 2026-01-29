<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TelegramNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 1) Har kuni 9:00 (Toshkent): bugungi o'yinlar ro'yxatini obunachilarga yuboradi (o'yinlar bo'lsa).
 * 2) O'yin boshlanishiga 1 soat qolganida: "1 soat qoldi" bildirishnomasini yuboradi.
 *
 * Cron: har daqiqada ishlatish kerak, masalan:
 *   * * * * * cd /path && php bin/console app:notify-users
 *
 * 9:00 faqat Toshkent vaqtida (server vaqti boshqa bo'lishi mumkin).
 */
#[AsCommand(
    name: 'app:notify-users',
    description: '9:00 da bugungi o\'yinlar + o\'yindan 1 soat oldin bildirishnoma yuboradi. Har daqiqa ishlatiladi.',
)]
final class NotifyUsersCommand extends Command
{
    public function __construct(
        private readonly TelegramNotificationService $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Qo\'lda ishlatilganda: soat 9 ga qaramay bugungi o\'yinlarni yuboradi (agar bugun yuborilmagan bo\'lsa)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool) $input->getOption('force');
        $this->notifier->sendDailyMatchesIfNeeded($force);
        $this->notifier->sendMatchReminders();

        return Command::SUCCESS;
    }
}
