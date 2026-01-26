# ИНСТРУКЦИЯ ПО ДЕПЛОЮ НА VERCEL

**Дата:** 2026-01-26  
**Проект:** welcomebaby.am  
**Платформа:** Vercel

---

## ✅ ПРОВЕРКА ПРОЕКТА

### Это Next.js проект?
**ДА!** ✅
- Next.js 15.5.9
- App Router
- TypeScript
- Prisma ORM
- PostgreSQL (Neon)

**Проект полностью совместим с Vercel!**

---

## 📋 ЧТО НУЖНО СДЕЛАТЬ

### 1. Подготовка .env для Vercel

**Текущий .env (локальный):**
```env
NODE_ENV=development
DATABASE_URL="postgresql://..."
NEXTAUTH_URL=http://localhost:3000
NEXTAUTH_SECRET=dev-secret-key-please-change-in-production
NEXT_PUBLIC_API_URL=http://localhost:3000/api
NEXT_PUBLIC_SITE_URL=http://localhost:3000
PORT=3000
```

**Что лишнее для Vercel:**
- ❌ `PORT=3000` - Vercel сам определяет порт
- ❌ `NEXT_PUBLIC_API_URL` - не нужен (используется относительный путь `/api`)
- ❌ `NEXT_PUBLIC_SITE_URL` - можно убрать (Vercel автоматически определяет)

**Что нужно изменить:**
- ✅ `NEXTAUTH_URL` - на production URL
- ✅ `NEXTAUTH_SECRET` - на сложный секрет (минимум 32 символа)
- ✅ `DATABASE_URL` - уже настроен на Neon (отлично!)

---

## 🚀 ШАГИ ДЛЯ ДЕПЛОЯ

### Шаг 1: Подготовить проект

1. **Убедиться что проект собирается:**
```bash
npm run build
```

2. **Проверить что нет критических ошибок:**
```bash
npm run type-check
npm run lint
```

### Шаг 2: Создать аккаунт на Vercel

1. Зайти на https://vercel.com
2. Войти через GitHub
3. Подключить репозиторий `neetrino-com/welcomebaby.am`

### Шаг 3: Настроить переменные окружения в Vercel

В настройках проекта → Environment Variables добавить:

```env
# Database (уже есть в .env)
DATABASE_URL=postgresql://neondb_owner:npg_79qxjgetEKAG@ep-divine-lab-ag5dnvod-pooler.c-2.eu-central-1.aws.neon.tech/neondb?sslmode=require

# NextAuth
NEXTAUTH_URL=https://your-domain.vercel.app
NEXTAUTH_SECRET=your-super-secret-key-minimum-32-characters-long

# Vercel Blob (после настройки)
BLOB_READ_WRITE_TOKEN=your-blob-token

# Environment
NODE_ENV=production
```

### Шаг 4: Настроить Vercel Blob Storage

1. **Установить Vercel Blob:**
```bash
npm install @vercel/blob
```

2. **Создать Blob Store в Vercel:**
   - Зайти в Vercel Dashboard
   - Storage → Create → Blob
   - Создать store (например: `welcomebaby-files`)

3. **Получить токен:**
   - В настройках Blob Store → Tokens
   - Создать токен с правами `read-write`
   - Добавить в Environment Variables как `BLOB_READ_WRITE_TOKEN`

### Шаг 5: Обновить код для использования Blob

Нужно обновить:
- `src/app/api/upload-image/route.ts`
- `src/app/api/upload/route.ts`
- `src/app/api/upload-logo/route.ts`

---

## 📦 НАСТРОЙКА VERCEL BLOB

### Установка пакета:
```bash
npm install @vercel/blob
```

### Обновление upload routes:

**Пример для upload-image/route.ts:**
```typescript
import { put } from '@vercel/blob'

// Вместо writeFile использовать:
const blob = await put(fileName, file, {
  access: 'public',
  token: process.env.BLOB_READ_WRITE_TOKEN
})

// Вернуть URL
return NextResponse.json({
  success: true,
  path: blob.url,
  fileName: fileName
})
```

---

## ⚙️ НАСТРОЙКА VERCEL

### Build Settings:
- **Framework Preset:** Next.js
- **Build Command:** `npm run build` (по умолчанию)
- **Output Directory:** `.next` (по умолчанию)
- **Install Command:** `npm install` (по умолчанию)

### Environment Variables:
- Все переменные из .env (кроме PORT, NEXT_PUBLIC_API_URL, NEXT_PUBLIC_SITE_URL)

### Prisma:
Vercel автоматически запустит:
- `prisma generate` во время build
- Нужно применить миграции вручную или через Vercel Postgres

---

## 🔧 ОБНОВЛЕНИЕ .ENV

**Для локальной разработки оставить:**
```env
NODE_ENV=development
DATABASE_URL="postgresql://..."
NEXTAUTH_URL=http://localhost:3000
NEXTAUTH_SECRET=dev-secret-key-please-change-in-production
```

**Для Vercel (Environment Variables):**
```env
NODE_ENV=production
DATABASE_URL="postgresql://..."
NEXTAUTH_URL=https://your-domain.vercel.app
NEXTAUTH_SECRET=your-production-secret-min-32-chars
BLOB_READ_WRITE_TOKEN=your-blob-token
```

---

## ✅ CHECKLIST ПЕРЕД ДЕПЛОЕМ

- [ ] Проект собирается (`npm run build`)
- [ ] Нет критических TypeScript ошибок
- [ ] DATABASE_URL настроен (Neon PostgreSQL)
- [ ] NEXTAUTH_SECRET изменен на production
- [ ] NEXTAUTH_URL настроен на production URL
- [ ] Vercel Blob установлен и настроен
- [ ] Upload routes обновлены для Blob
- [ ] Переменные окружения добавлены в Vercel

---

## 🚨 ВАЖНЫЕ ЗАМЕЧАНИЯ

1. **Файлы в public/images:**
   - На Vercel файловая система read-only
   - Нужно использовать Vercel Blob для загрузки файлов
   - Статические файлы можно оставить в `public/`

2. **Prisma Migrations:**
   - Применить миграции вручную на Neon
   - Или использовать `prisma migrate deploy` в Vercel

3. **NEXTAUTH_SECRET:**
   - Должен быть минимум 32 символа
   - Генерировать: `openssl rand -base64 32`

---

**Готов к настройке Blob и обновлению кода?** ✅
