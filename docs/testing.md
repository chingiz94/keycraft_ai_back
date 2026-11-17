# Протокол проверки КП10

Дата: 2026-11-11T18:41:28.859Z. Postman CLI 1.88.0; живой API Laravel / MySQL на отдельной базе keycraft_demo. Облачная отправка отчётов отключена (--no-report-events). Результат: **46 запросов, 67 утверждений, ошибок 0**.

Коллекция: [KeyCraft.coursework-tests.postman_collection.json](KeyCraft.coursework-tests.postman_collection.json). Это автоматизированный запуск Postman CLI; браузерные действия вручную проверены отдельно в [протоколе клиента](client-verification.md). Не выдаётся за ручной запуск каждого запроса в интерфейсе Postman. Имена полей в примерах совпадают с контрактом; числовые ID цепочки подставлялись из реальных ответов. Пароли и токены в экспорт не включены.

| № | Проверка | Метод и URL | Входные данные | Ожидаемый HTTP | Фактический HTTP | Результат |
|---|---|---|---|---|---|---|
| 1 | API и база доступны | GET /api/v1/health | — | 200 | 200 | Пройдено (2) |
| 2 | Гость не читает сборки | GET /api/v1/builds | — | 401 | 401 | Пройдено (1) |
| 3 | Вход администратора | POST /api/v1/auth/login | { "email": "{{admin_email}}", "password":"<локальное значение>" } | 200 | 200 | Пройдено (2) |
| 4 | Роль нельзя назначить регистрацией | POST /api/v1/auth/register | { "name": "Проверка КП10", "email": "{{test_email}}", "password":"<локальное значение>", "password_confirmation":"<локальное зн… | 422 | 422 | Пройдено (2) |
| 5 | Регистрация покупателя | POST /api/v1/auth/register | { "name": "Проверка КП10", "email": "{{test_email}}", "password":"<локальное значение>", "password_confirmation":"<локальное зн… | 201 | 201 | Пройдено (3) |
| 6 | Текущий пользователь | GET /api/v1/auth/me | — | 200 | 200 | Пройдено (2) |
| 7 | Покупателю запрещено администрирование | GET /api/v1/admin/users | — | 403 | 403 | Пройдено (1) |
| 8 | Валидация компонента | POST /api/v1/admin/components | { "name": "", "type": "unknown", "price": -1, "stock": -1, "specs": {} } | 422 | 422 | Пройдено (2) |
| 9 | Создать тестовый case | POST /api/v1/admin/components | { "name": "CP10 {{run_id}} case", "type": "case", "price": 10000, "stock": 1000, "format": "65", "specs": { "mount": "gasket" } } | 201 | 201 | Пройдено (1) |
| 10 | Создать тестовый pcb | POST /api/v1/admin/components | { "name": "CP10 {{run_id}} pcb", "type": "pcb", "price": 7000, "stock": 1000, "format": "65", "specs": { "mount": "gasket", "sw… | 201 | 201 | Пройдено (1) |
| 11 | Создать тестовый plate | POST /api/v1/admin/components | { "name": "CP10 {{run_id}} plate", "type": "plate", "price": 4000, "stock": 1000, "format": "65", "specs": { "mount": "gasket",… | 201 | 201 | Пройдено (1) |
| 12 | Создать тестовый stabilizer | POST /api/v1/admin/components | { "name": "CP10 {{run_id}} stabilizer", "type": "stabilizer", "price": 2000, "stock": 1000, "format": null, "specs": { "stabili… | 201 | 201 | Пройдено (1) |
| 13 | Создать тестовый switch | POST /api/v1/admin/components | { "name": "CP10 {{run_id}} switch", "type": "switch", "price": 100, "stock": 1000, "format": null, "specs": { "switch_type": "m… | 201 | 201 | Пройдено (1) |
| 14 | Создать тестовый keycap | POST /api/v1/admin/components | { "name": "CP10 {{run_id}} keycap", "type": "keycap", "price": 5000, "stock": 1000, "format": null, "specs": { "stem": "mx", "l… | 201 | 201 | Пройдено (1) |
| 15 | Поиск фильтрация и пагинация | GET /api/v1/components?search={{run_id}}&type=case&in_stock=1&sort=price&order=asc&per_page=1&page=1 | — | 200 | 200 | Пройдено (2) |
| 16 | Карточка и связанные предложения | GET /api/v1/components/{{case_id}} | — | 200 | 200 | Пройдено (2) |
| 17 | Создать предложение | POST /api/v1/admin/offers | { "component_id": {{case_id}}, "marketplace": "Учебный магазин", "url": "https://example.test/keycraft", "price": 9000, "price_… | 201 | 201 | Пройдено (1) |
| 18 | Получить предложения компонента | GET /api/v1/components/{{case_id}}/offers | — | 200 | 200 | Пройдено (2) |
| 19 | Изменить компонент | PATCH /api/v1/admin/components/{{case_id}} | { "name": "CP10 {{run_id}} case updated" } | 200 | 200 | Пройдено (1) |
| 20 | Создать сборку | POST /api/v1/builds | { "name": "CP10 {{run_id}} build", "budget": 1000000 } | 201 | 201 | Пройдено (1) |
| 21 | Неполная сборка не проходит проверку | POST /api/v1/builds/{{build_id}}/validate | — | 200 | 200 | Пройдено (2) |
| 22 | Добавить case в сборку | POST /api/v1/builds/{{build_id}}/items | { "component_id": {{case_id}}, "quantity": 1 } | 201 | 201 | Пройдено (1) |
| 23 | Добавить pcb в сборку | POST /api/v1/builds/{{build_id}}/items | { "component_id": {{pcb_id}}, "quantity": 1 } | 201 | 201 | Пройдено (1) |
| 24 | Добавить plate в сборку | POST /api/v1/builds/{{build_id}}/items | { "component_id": {{plate_id}}, "quantity": 1 } | 201 | 201 | Пройдено (1) |
| 25 | Добавить stabilizer в сборку | POST /api/v1/builds/{{build_id}}/items | { "component_id": {{stabilizer_id}}, "quantity": 1 } | 201 | 201 | Пройдено (1) |
| 26 | Добавить switch в сборку | POST /api/v1/builds/{{build_id}}/items | { "component_id": {{switch_id}}, "quantity": 68 } | 201 | 201 | Пройдено (1) |
| 27 | Добавить keycap в сборку | POST /api/v1/builds/{{build_id}}/items | { "component_id": {{keycap_id}}, "quantity": 1 } | 201 | 201 | Пройдено (1) |
| 28 | Повтор детали запрещён | POST /api/v1/builds/{{build_id}}/items | { "component_id": {{case_id}}, "quantity": 1 } | 409 | 409 | Пройдено (1) |
| 29 | Совместимая сборка и серверная сумма | POST /api/v1/builds/{{build_id}}/validate | — | 200 | 200 | Пройдено (2) |
| 30 | Устаревшая сумма не создаёт заказ | POST /api/v1/orders | { "build_id": {{build_id}}, "expected_total": 1 } | 409 | 409 | Пройдено (2) |
| 31 | Оформить заказ | POST /api/v1/orders | { "build_id": {{build_id}}, "expected_total": 34800 } | 201 | 201 | Пройдено (2) |
| 32 | Повтор не создаёт второй заказ | POST /api/v1/orders | { "build_id": {{build_id}}, "expected_total": 34800 } | 200 | 200 | Пройдено (2) |
| 33 | Остаток списан один раз | GET /api/v1/components/{{switch_id}} | — | 200 | 200 | Пройдено (2) |
| 34 | Пропуск статуса запрещён | PATCH /api/v1/admin/orders/{{order_id}}/status | { "status": "issued" } | 409 | 409 | Пройдено (1) |
| 35 | Отмена возвращает остатки | PATCH /api/v1/admin/orders/{{order_id}}/status | { "status": "cancelled" } | 200 | 200 | Пройдено (2) |
| 36 | Повторная отмена запрещена | PATCH /api/v1/admin/orders/{{order_id}}/status | { "status": "cancelled" } | 409 | 409 | Пройдено (1) |
| 37 | Остаток возвращён ровно один раз | GET /api/v1/components/{{switch_id}} | — | 200 | 200 | Пройдено (2) |
| 38 | Связанный с заказом компонент не удаляется | DELETE /api/v1/admin/components/{{case_id}} | — | 409 | 409 | Пройдено (1) |
| 39 | Статистика доступна администратору | GET /api/v1/admin/statistics | — | 200 | 200 | Пройдено (2) |
| 40 | Изменить предложение | PATCH /api/v1/admin/offers/{{offer_id}} | { "price": 8500 } | 200 | 200 | Пройдено (2) |
| 41 | Удалить созданное предложение | DELETE /api/v1/admin/offers/{{offer_id}} | — | 204 | 204 | Пройдено (1) |
| 42 | Удалить сборку с сохранением заказа | DELETE /api/v1/builds/{{build_id}} | — | 204 | 204 | Пройдено (1) |
| 43 | Заказ сохраняется после удаления сборки | GET /api/v1/orders/{{order_id}} | — | 200 | 200 | Пройдено (2) |
| 44 | Неизвестный ресурс | GET /api/v1/components/2147483647 | — | 404 | 404 | Пройдено (1) |
| 45 | Выход покупателя | POST /api/v1/auth/logout | — | 204 | 204 | Пройдено (1) |
| 46 | Отозванный токен больше не действует | GET /api/v1/auth/me | — | 401 | 401 | Пройдено (1) |

Feature-тесты Laravel: 56 тестов, 309 утверждений. Тесты общего клиента API: 6, пройдены. Проверки ответа ИИ выполняются с Http::fake; реальный провайдер в этом прогоне не вызывается. События order.created фиксируются после commit; тест отката проверяет отсутствие такого события. Журнал API хранит только метод, путь без query, user_id, статус, длительность и request_id.

Полные отчёты Postman CLI содержат временные значения авторизации и остаются локально в work, вне Git и архива. В [api-tests.json](evidence/api-tests.json) сохранены только обезличенные результаты. Первый пробный прогон выявил ошибку экранирования в предварительном скрипте коллекции; после исправления синтаксис всех скриптов проверяется генератором до запуска. Итоговая таблица относится к завершённому повторному прогону.
