# KeyCraft AI

Веб-конструктор механических клавиатур. Интерфейс работает на Next.js, API — на Laravel с MySQL.

## Что нужно для запуска

- PHP 8.3 или новее с `pdo_mysql` и Composer 2
- MySQL 8.4
- Node.js 24 и npm

## Первый запуск

1. Запустите MySQL. Создайте пустую базу `keycraft` и пользователя с правами на неё.
2. Из корня проекта установите зависимости Laravel и создайте локальную конфигурацию.

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. В `.env` укажите `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` и `DB_PASSWORD`. Для входа в панель администратора задайте `DEMO_ADMIN_PASSWORD` длиной от 8 символов. Учётная запись создаётся при заполнении базы, её адрес — `admin@keycraft.test`.
4. Примените миграции и соберите интерфейс.

   ```powershell
   php artisan migrate --seed
   cd frontend
   npm ci
   npm run build
   cd ..
   ```

5. В Windows запустите `.\Start-KeyCraft.ps1`. Откройте [сайт](http://localhost:5173) или [документацию API](http://127.0.0.1:8120/docs).

## Запуск из двух терминалов

В первом терминале из корня проекта запустите API.

```shell
php artisan serve --host=127.0.0.1 --port=8120 --no-reload
```

Во втором терминале откройте `frontend` и выполните `npm start`. Для разработки используйте `npm run dev`.

По умолчанию Next.js обращается к API по адресу `http://127.0.0.1:8120/api/v1`. При смене адреса API задайте `NEXT_PUBLIC_API_BASE_URL` в `frontend/.env.local` и пересоберите интерфейс. Для другого адреса сайта задайте `FRONTEND_URL` в серверной `.env`.

## Подключение ИИ

Для Groq задайте в `.env` `AI_PROVIDER=groq` и `GROQ_API_KEY`. Для OpenAI задайте `AI_PROVIDER=openai` и `OPENAI_API_KEY`. После изменения настроек выполните `php artisan config:clear`.

