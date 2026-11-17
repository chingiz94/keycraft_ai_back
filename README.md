# KeyCraft AI

Веб-конструктор механических клавиатур с реальными комплектующими, прямыми ссылками на магазины Казахстана и ИИ-подбором.

Frontend: **Алимканов Жасулан**, ИС-24-22б. Next.js 16, React Context + useReducer, TypeScript, Tailwind CSS 4, GSAP 3. Готовый backend: **Нукушев Чингиз**, Laravel 13, Sanctum, MySQL 8.4. Доработки розничного каталога и его API включены в этот репозиторий.

## Что работает

- Главная с анимированной 3D клавиатурой, каталог и описание сервиса.
- 20 реальных моделей (5 основ, 8 свитчей, 7 наборов кейкапов), 27 предложений Белого Ветра, Vendara и Over-Shop; локальные фото, поиск, фильтры, сортировка, пагинация.
- Основы GMMK 2 на 67/99 клавиш и GMMK PRO на 82 клавиши, MX свитчи Glorious/Gateron/HyperX и наборы кейкапов. Основа содержит корпус, плату, плейт и стабилизаторы. Сборщик считает целые упаковки и запас, отмечает неподтверждённое покрытие раскладки сторонними кейкапами.
- Черновик в браузере, сохранение и редактирование списков в личном аккаунте, регистрация/вход/выход.
- ИИ через серверный Groq или OpenAI; проверка выбранных ID, бюджета и совместимости. При отказе — понятная ошибка, самостоятельный конструктор остаётся доступен.
- Административное SPA с пятью разделами: обзор, комплектующие, предложения, заказы, пользователи. CRUD, роли и переходы статусов через настоящий API.
- Адаптивный интерфейс, уведомления, skeleton и состояния ошибок/пустых результатов; prefers-reduced-motion.

Розничный каталог — снимок карточек продавцов на **18.11.2026**, а не live остатки. Цена в городе, доставка и оплата подтверждаются магазином по прямой ссылке. Сохранение списка не создаёт заказ у продавца. Отдельный учебный склад Laravel и его заказы доступны администратору; они не смешиваются с розничными списками.

## Запуск на Windows

Нужны PHP 8.3+ с pdo_mysql, mbstring и openssl, Composer2, MySQL 8.4, Node.js24 LTS. На проверенном стенде PHP 8.5.1 и Node 24.14.1.

1. Запустите MySQL, создайте пустую базу `keycraft` и пользователя с правами на неё.
2. В корне проекта:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

3. Настройте DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD в `.env`. Оставьте `FRONTEND_URL=http://localhost:5173`. Для учебных аккаунтов задайте разные DEMO_ADMIN_PASSWORD и DEMO_BUYER_PASSWORD длиной от 8 символов.
4. Выполните:

```powershell
php artisan migrate --seed
# Необязательно: пять учебных складских заказов для демонстрации админки
php artisan db:seed --class=CourseworkSeeder
cd frontend
npm ci
Copy-Item .env.example .env.local
npm run build
cd ..
.\Start-KeyCraft.ps1
```

Откройте **http://localhost:5173**. Учебные email: `buyer@keycraft.test` и `admin@keycraft.test`, пароли — заданные в локальной `.env`. Seeder не меняет пароль существующего пользователя. Новый покупатель также может зарегистрироваться через сайт.

Для запуска вручную нужны два терминала:

```powershell
# Корень Laravel
php artisan serve --host=127.0.0.1 --port=8120 --no-reload
# Второй терминал, папка frontend
npm start
```

Для разработки вместо `npm start` используйте `npm run dev`; скрипт запуска также принимает `-Development`. MySQL запускается отдельно. Если порт занят, проверьте процесс и журналы, не запускайте второй MySQL на той же папке данных.

## ИИ

В серверной `.env` укажите `AI_PROVIDER=groq`, свой `GROQ_API_KEY` и `GROQ_MODEL=openai/gpt-oss-20b`, затем `php artisan config:clear`. Доступность бесплатной квоты определяется вашим аккаунтом Groq. Поддерживается отдельный вариант OpenAI через его собственные переменные. Автоматического перехода к другому провайдеру нет. Ключи не передаются frontend и не включены в Git. Без ключа каталог, конструктор и сохранение работают, ИИ сообщает 503.

## Структура

- `frontend/src/app` — маршруты Next.js; `components` — общие элементы, Context, GSAP и формы; `lib` — типы и HTTP-клиент.
- `frontend/public/assets` — локальные фотографии и шрифт Manrope с лицензией.
- `app` — API контроллеры, FormRequest, ресурсы, policies и бизнес-сервисы Laravel.
- `resources/data/retail-catalog.json` — проверенные модели и товарные URL, цены в тиынах.
- `database` — миграции, factories, seeder, схема MySQL.
- `tests` и `frontend/tests` — серверные и клиентские тесты.
- `docs` — OpenAPI, переносимая коллекция Postman, требования и протоколы; `public/api-docs.*` — локальный просмотрщик.
- `client` — прежний демонстрационный vanilla-клиент Backend; основной интерфейс находится в `frontend`.

## Проверки и материалы

```powershell
php artisan test --compact
node docs/generate-api-docs.cjs
node docs/verify-api-docs.cjs
cd frontend
npm run lint
npm run typecheck
npm test
npm run build
npm audit --omit=dev
```

[Требования Classroom Frontend](docs/frontend-classroom.md) · [Проверки интерфейса](docs/frontend-verification.md) · [Источники товаров](docs/retail-sources.md) · [Frontend README](frontend/README.md).

[Материалы Алимканова Жасулана](docs/coursework/frontend/README.md): пояснительная записка DOCX/PDF, отчёты по этапам DOCX/PDF, презентация и сценарий защиты. [Архив материалов Backend](docs/coursework/backend) относится к Нукушеву Чингизу.

Локальная документация: http://127.0.0.1:8120/docs. Контракт содержит 44 операции; [OpenAPI](docs/openapi.json), [Postman](docs/KeyCraft.postman_collection.json). Пароли и token в коллекции пустые; вход сохраняет token автоматически. Документация Postman публично не опубликована. Сдача в Classroom и покупки в магазинах не выполнялись.
