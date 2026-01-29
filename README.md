# Sport o'yin vaqtlari

Symfony ilovasi va Telegram bot — **TOP-5 Yevropa ligalari** (Premier League, La Liga, Serie A, Bundesliga, Ligue 1) va **UEFA Champions League** o'yin vaqtlarini ko'rsatadi. Vaqtlar **O'zbekiston vaqti** (Toshkent) bo'yicha.

## Ma'lumot manbai

Yagona manba: [Football-Data.org](https://www.football-data.org) — TOP-5 liga + UCL. API key **majburiy**. [Ro'yxatdan o'tish](https://www.football-data.org/client/register). `.env` da `FOOTBALL_DATA_ORG_TOKEN=` ni to'ldiring. O'yin vaqtlari **O'zbekiston vaqti** (Toshkent) bo'yicha.

## Telegram bot

### Botni ishga tushirish

1. [@BotFather](https://t.me/BotFather) da yangi bot yarating va tokenni oling.
2. `.env` faylida `TELEGRAM_BOT_TOKEN` ni to'ldiring:
   ```env
   TELEGRAM_BOT_TOKEN=123456789:ABCdefGHI...
   ```
3. Botni ishga tushiring:
   ```bash
   php bin/console app:telegram-bot
   ```

### Foydalanish

- **Bugungi o'yinlar** yoki **/today** — bugungi barcha o'yinlar
- **Sana** (masalan: `2025-01-28`, `28.01.2025`, `28/01/2025`) — shu sanadagi o'yinlar
- **/start** — qisqa yo'riqnoma va bugungi o'yinlar

To'xtatish: `Ctrl+C`
