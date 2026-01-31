<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TelegramBotService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class TelegramWebhookController extends AbstractController
{
    public function __construct(
        private readonly TelegramBotService $telegramBot,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Telegram webhook: foydalanuvchi xabar yuboradi → javob shu endpoint orqali qaytadi.
     * GET: route ishlayotganini tekshirish uchun (brauzerda ochilsa 200 "ok" qaytadi).
     */
    public function handle(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return new Response('ok', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        }

        $body = $request->getContent();
        if ($body === '') {
            $this->logger->warning('Telegram webhook: bo\'sh body');
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $update = json_decode($body, true);
        if (!\is_array($update)) {
            $this->logger->warning('Telegram webhook: noto\'g\'ri JSON');
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $updateId = (int) ($update['update_id'] ?? 0);
        $this->logger->info('Telegram webhook: update qabul qilindi', ['update_id' => $updateId]);

        $processed = $this->telegramBot->processUpdate($update);
        if (null === $processed) {
            $this->logger->debug('Telegram webhook: processUpdate null (message yo\'q yoki chat_id 0)');
            return new Response('', Response::HTTP_OK);
        }

        [, $reply] = $processed;
        if (!\is_array($reply) || !isset($reply['chat_id'], $reply['text'])) {
            return new Response('', Response::HTTP_OK);
        }

        try {
            $this->telegramBot->sendMessage(
                (int) $reply['chat_id'],
                $reply['text'],
                false,
                $reply['reply_markup'] ?? null
            );
            $this->logger->info('Telegram webhook: javob yuborildi', ['chat_id' => $reply['chat_id']]);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Telegram webhook: sendMessage xatosi', [
                'chat_id' => $reply['chat_id'],
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Telegram webhook: kutilmagan xato', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return new Response('', Response::HTTP_OK);
    }
}
