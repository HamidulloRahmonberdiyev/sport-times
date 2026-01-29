<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TelegramBotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class TelegramWebhookController extends AbstractController
{
    public function __construct(
        private readonly TelegramBotService $telegramBot,
    ) {
    }

    /**
     * Telegram webhook: foydalanuvchi xabar yuboradi → javob shu endpoint orqali qaytadi.
     */
    public function handle(Request $request): Response
    {
        $body = $request->getContent();
        if ($body === '') {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $update = json_decode($body, true);
        if (!\is_array($update)) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $processed = $this->telegramBot->processUpdate($update);
        if (null === $processed) {
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
        } catch (ExceptionInterface) {
            // Javob yuborishda xato — 200 qaytaramiz, Telegram qayta urinmaydi
        }

        return new Response('', Response::HTTP_OK);
    }
}
