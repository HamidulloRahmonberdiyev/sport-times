<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TelegramBotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

#[AsCommand(
    name: 'app:telegram-bot',
    description: 'Sport o\'yin vaqtlari Telegram botini ishga tushiradi (long polling)',
)]
final class TelegramBotCommand extends Command
{
    public function __construct(
        private readonly TelegramBotService $telegramBot,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'TELEGRAM_BOT_TOKEN')]
        private readonly string $botToken,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'timeout',
            't',
            InputOption::VALUE_OPTIONAL,
            'Long poll timeout (sekund)',
            25,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('' === $this->botToken || 'YOUR_BOT_TOKEN' === $this->botToken) {
            $io->error(
                "TELEGRAM_BOT_TOKEN o'rnatilmagan. .env faylida TELEGRAM_BOT_TOKEN=... qo'shing. "
                . "Tokenni @BotFather orqali olishingiz mumkin."
            );
            return Command::FAILURE;
        }

        $timeout = (int) $input->getOption('timeout');
        $offset = 0;
        $consecutive409 = 0;
        $max409 = 5;

        try {
            $this->telegramBot->deleteWebhook(true);
        } catch (ExceptionInterface $e) {
            $io->warning('deleteWebhook xatosi ( davom etiladi ): ' . $e->getMessage());
        }
        sleep(2);

        $io->success('Telegram bot ishga tushdi. To\'xtatish uchun Ctrl+C bosing.');

        while (true) {
            try {
                $updates = $this->telegramBot->getUpdates($offset, $timeout);
                $consecutive409 = 0;
            } catch (ExceptionInterface $e) {
                $msg = $e->getMessage();
                $is409 = str_contains($msg, '409');
                if ($is409) {
                    $consecutive409++;
                    $io->warning("409 Conflict ({$consecutive409}/{$max409}): boshqa getUpdates yoki webhook. deleteWebhook → 5 s…");
                    if ($consecutive409 >= $max409) {
                        $io->error(
                            "409 {$max409} marta ketma-ket. Bot boshqa joyda ishlayapti.\n\n"
                            . "• Boshqa terminaldagi app:telegram-bot ni to'xtating (Ctrl+C)\n"
                            . "• pgrep -af telegram-bot → kill <PID>\n"
                            . "• Boshqa server/qurilmada shu token ishlatilmasin\n"
                            . "• Keyin: php bin/console app:telegram-bot"
                        );
                        return Command::FAILURE;
                    }
                    try {
                        $this->telegramBot->deleteWebhook(true);
                    } catch (ExceptionInterface) {
                        // ignore
                    }
                    sleep(5);
                } else {
                    $consecutive409 = 0;
                    $io->warning('getUpdates xatosi: ' . $msg);
                    sleep(2);
                }
                continue;
            }

            foreach ($updates as $update) {
                $processed = $this->telegramBot->processUpdate($update);
                if (null === $processed) {
                    $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
                    continue;
                }

                [$nextOffset, $reply] = $processed;
                $offset = $nextOffset + 1;

                if (\is_array($reply) && isset($reply['chat_id'], $reply['text'])) {
                    try {
                        $this->telegramBot->sendMessage(
                            (int) $reply['chat_id'],
                            $reply['text'],
                            false,
                            $reply['reply_markup'] ?? null
                        );
                    } catch (ExceptionInterface $e) {
                        $io->warning('sendMessage xatosi: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}
