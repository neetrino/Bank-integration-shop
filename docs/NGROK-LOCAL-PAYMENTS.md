# Локальное тестирование платежей через ngrok

Callback'и от Idram (и при необходимости от банков) приходят на **публичный URL**. С `localhost` они не работают — нужен туннель.

## 1. Установка и авторизация (один раз)

Уже сделано: аккаунт ngrok, установка (например `brew install ngrok`), авторизация:

```bash
ngrok config add-authtoken YOUR_AUTH_TOKEN
```

Токен берётся в [dashboard.ngrok.com](https://dashboard.ngrok.com) → Your Authtoken.

## 2. Запуск туннеля на порт 3000

Next.js по умолчанию слушает порт **3000**. В дашборде ngrok показан пример с портом 80 — для этого проекта нужен **3000**.

**Терминал 1 — приложение:**
```bash
cd "/Users/user/2. Projects - DevTest/Bank-integration-shop/welcomebaby.am"
npm run dev
```

**Терминал 2 — ngrok:**
```bash
ngrok http 3000
```

В выводе ngrok появится строка вида:
```text
Forwarding    https://xxxx-xx-xx-xx-xx.ngrok-free.app -> http://localhost:3000
```

Скопируйте этот `https://....ngrok-free.app` — это ваш публичный URL.

## 3. Настройка .env

В `.env` укажите этот URL как базовый (для NextAuth и callback'ов):

```env
NEXTAUTH_URL=https://ВАШ-URL-ИЗ-NGROK.ngrok-free.app
```

Пример:
```env
NEXTAUTH_URL=https://nonregenerative-caprice-terrificallly.ngrok-free.app
```

Перезапустите `npm run dev` после изменения `.env`.

## 4. Регистрация URL у Idram

Передайте техническому персоналу Idram **три URL** с вашим ngrok-доменом:

| Параметр    | URL |
|------------|-----|
| RESULT_URL | `https://ВАШ-NGROK-URL.ngrok-free.app/api/payments/idram/callback` |
| SUCCESS_URL | `https://ВАШ-NGROK-URL.ngrok-free.app/order-success?clearCart=true` |
| FAIL_URL   | `https://ВАШ-NGROK-URL.ngrok-free.app/order-success?error=payment_failed` |

После регистрации можно тестировать оплату IDram: заходите на сайт по ngrok-URL и оформляйте заказ с оплатой IDram.

## 5. Важно

- Бесплатный ngrok при каждом новом запуске даёт **новый URL**. После перезапуска ngrok нужно снова обновить `NEXTAUTH_URL` в `.env` и передать новые URL в Idram (или использовать платный фиксированный домен ngrok).
- Оба процесса должны быть запущены: **Next.js** (`npm run dev`) и **ngrok** (`ngrok http 3000`).
- Открывать сайт для теста нужно по **ngrok-URL** (не localhost), чтобы редиректы и callback'и шли на один и тот же домен.
