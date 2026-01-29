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
    description: 'Telegram webhook ni o\'rnatish yoki o\'chirish (xabarlar webhook orqali qayta ishlanadi)',
)]
final class TelegramBotCommand extends Command
{
    private const WEBHOOK_PATH = '/webhook/telegram';

    public function __construct(
        private readonly TelegramBotService $telegramBot,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'TELEGRAM_BOT_TOKEN')]
        private readonly string $botToken,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'TELEGRAM_WEBHOOK_URL')]
        private readonly string $webhookBaseUrl,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'DEFAULT_URI')]
        private readonly string $defaultUri,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'set-webhook',
                null,
                InputOption::VALUE_NONE,
                'Webhook URL ni Telegram ga o\'rnatadi',
            )
            ->addOption(
                'delete-webhook',
                null,
                InputOption::VALUE_NONE,
                'Webhook ni o\'chiradi',
            )
            ->addOption(
                'info',
                null,
                InputOption::VALUE_NONE,
                'Telegram da o\'rnatilgan webhook URL ni ko\'rsatadi',
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

        $setWebhook = $input->getOption('set-webhook');
        $deleteWebhook = $input->getOption('delete-webhook');
        $info = $input->getOption('info');

        if ($info) {
            try {
                $webhookInfo = $this->telegramBot->getWebhookInfo();
                $io->title('Webhook ma\'lumoti');
                $io->table(
                    ['Parametr', 'Qiymat'],
                    [
                        ['URL', $webhookInfo['url'] !== '' ? $webhookInfo['url'] : '(o\'rnatilmagan)'],
                        ['Pending updates', (string) $webhookInfo['pending_update_count']],
                    ]
                );
                if ($webhookInfo['url'] !== '' && !str_starts_with($webhookInfo['url'], 'https://')) {
                    $io->warning('Telegram webhook uchun HTTPS talab qiladi. HTTP faqat localhost uchun ishlashi mumkin.');
                }
            } catch (ExceptionInterface $e) {
                $io->error('Webhook ma\'lumotini olishda xato: ' . $e->getMessage());
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        }

        if ($deleteWebhook) {
            try {
                $this->telegramBot->deleteWebhook(true);
                $io->success('Webhook o\'chirildi.');
            } catch (ExceptionInterface $e) {
                $io->error('Webhook o\'chirishda xato: ' . $e->getMessage());
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        }

        if ($setWebhook) {
            $baseUrl = $this->webhookBaseUrl !== '' ? $this->webhookBaseUrl : $this->defaultUri;
            $baseUrl = rtrim($baseUrl, '/');
            $webhookUrl = $baseUrl . self::WEBHOOK_PATH;

            if (!str_starts_with($webhookUrl, 'https://') && !str_starts_with($webhookUrl, 'http://127.0.0.1')) {
                $io->warning(
                    'Telegram webhook uchun odatda HTTPS kerak. Domain internetdan ochiq va SSL bo\'lishi kerak. '
                    . 'Local test uchun ngrok ishlatishingiz mumkin.'
                );
            }

            try {
                $this->telegramBot->setWebhook($webhookUrl);
                $io->success('Webhook o\'rnatildi: ' . $webhookUrl);
                $io->note('Foydalanuvchi xabarlari endi shu URL ga POST qilinadi. Command ishga tushirish shart emas.');
            } catch (ExceptionInterface $e) {
                $io->error('Webhook o\'rnatishda xato: ' . $e->getMessage());
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        }

        $io->title('Telegram bot — webhook rejimi');
        $io->text([
            'Foydalanuvchi xabarlari <comment>webhook</comment> orqali qayta ishlanadi (command emas).',
            '',
            'Webhook ni o\'rnatish:',
            '  <info>php bin/console app:telegram-bot --set-webhook</info>',
            '',
            'Buning uchun .env da quyidagilardan biri bo\'lishi kerak:',
            '  • <comment>TELEGRAM_WEBHOOK_URL</comment> — to\'liq asosiy URL (masalan: https://yourdomain.com)',
            '  • yoki <comment>DEFAULT_URI</comment> — URL (masalan: http://localhost)',
            '',
            'Webhook ni o\'chirish:',
            '  <info>php bin/console app:telegram-bot --delete-webhook</info>',
            '',
            'O\'rnatilgan webhook URL ni tekshirish:',
            '  <info>php bin/console app:telegram-bot --info</info>',
        ]);

        return Command::SUCCESS;
    }
}
