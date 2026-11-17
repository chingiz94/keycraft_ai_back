# KeyCraft AI Frontend

Курсовая работа Алимканова Жасулана, ИС-24-22б, «Основы frontend». Next.js App Router, TypeScript, React Context + useReducer, Tailwind CSS и GSAP. Готовый Laravel backend — Нукушев Чингиз; доработки для розничного каталога находятся в том же репозитории.

## Запуск

Нужны Node.js 24 LTS, запущенный Laravel API и MySQL. Из этой папки:

```powershell
npm ci
Copy-Item .env.example .env.local
npm run build
npm start
```

Открыть http://localhost:5173. Для разработки: `npm run dev`. Адрес API по умолчанию — http://127.0.0.1:8120/api/v1. При изменении `NEXT_PUBLIC_API_BASE_URL` повторите сборку; `FRONTEND_URL` сервера должен совпадать с origin клиента. Публичная переменная содержит только URL, ключи ИИ в неё не помещаются.

## Страницы

| Маршрут | Назначение |
|---|---|
| `/` | Главная, интерактивная клавиатура GSAP |
| `/catalog` | Восемь реальных моделей, поиск, фильтры, сортировка, страницы по шесть записей |
| `/guide` | Описание сервиса и процесса покупки |
| `/builder` | Выбор основы, свитчей и кейкапов, расчёт целых упаковок |
| `/login`, `/register` | Настоящая авторизация API |
| `/account/builds` | Личные списки покупок, открытие и удаление |
| `/ai` | Подбор через настроенный сервером Groq или OpenAI |
| `/admin`, `/admin/components`, `/admin/offers`, `/admin/orders`, `/admin/users` | Пять разделов административного SPA |

Context в `src/components/store.tsx` хранит пользователя, каталог, черновик и уведомления. Черновик переживает перезагрузку в localStorage, Bearer token живёт в sessionStorage и удаляется после выхода/401. Реальные права проверяет API. Суммы API — целые тиыны, интерфейс показывает тенге.

Розничный каталог — проверенный вручную снимок `resources/data/retail-catalog.json`. Это отдельные предложения магазинов, а не склад Laravel. Панель администратора управляет учебным складом и его заказами. Покупка розничных товаров оформляется по прямой ссылке у продавца. Список не резервирует товар и не списывает деньги. Дата проверки видна; спустя семь дней API помечает снимок устаревшим.

## Проверки

```powershell
npm run lint
npm run typecheck
npm test
npm run build
npm audit --omit=dev
```

Шесть тестов HTTP-клиента проверяют Bearer, публичные запросы, 401, ошибки полей и request ID, 204 и сетевые отказы. Node 24 выводит предупреждение об экспериментальном преобразовании TypeScript при тестировании. [Протокол интерфейса](../docs/frontend-verification.md), [соответствие Classroom](../docs/frontend-classroom.md).

GSAP реализован в `src/components/hero.tsx`: useGSAP, scope, contextSafe, timeline, matchMedia и ScrollTrigger с очисткой. Анимации отключаются при prefers-reduced-motion. Наведение работает только на устройствах с точным указателем. Шрифты и фотографии хранятся локально; [источники](../docs/retail-sources.md).
