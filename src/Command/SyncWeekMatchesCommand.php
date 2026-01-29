<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SyncWeekMatchesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bir haftalik (7 kun) TOP‑5 + UCL o'yinlarini Football-Data.org dan yuklab
 * club, competition, match jadvallariga create/update qiladi.
 * Har kuni bir marta ishlatiladi (cron: 0 6 * * * yoki kerakli vaqt).
 */
#[AsCommand(
    name: 'app:sync-week-matches',
    description: 'Bir haftalik TOP‑5 va UCL o\'yinlarini DB ga sinxronlaydi (create/update). Har kuni 1 marta ishlatiladi.',
)]
final class SyncWeekMatchesCommand extends Command
{
    public function __construct(
        private readonly SyncWeekMatchesService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'from',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Boshlash sanasi (Y-m-d). Berilmasa bugun.',
                null,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $from = null;
        $fromStr = $input->getOption('from');
        if (\is_string($fromStr) && $fromStr !== '') {
            $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromStr, new \DateTimeZone('Asia/Tashkent'));
            if (!$from instanceof \DateTimeImmutable) {
                $io->error('--from formati noto‘g‘ri. Masalan: 2025-01-28');
                return Command::FAILURE;
            }
        }

        $io->info('Bir haftalik o\'yinlar sinxronlanmoqda...');

        $result = $this->syncService->syncWeek($from);

        $io->success([
            'Yangi: ' . $result['created'],
            'Yangilangan: ' . $result['updated'],
            'O\'tkazib yuborilgan: ' . $result['skipped'],
        ]);

        if (\count($result['errors']) > 0) {
            $io->warning('Xatoliklar:');
            $io->listing($result['errors']);
        }

        return Command::SUCCESS;
    }
}
