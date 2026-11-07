# База данных KeyCraft AI

СУБД для запуска — MySQL 8.4 (InnoDB). Миграции Laravel создают физическую схему. Семь предметных сущностей: пользователь, компонент, предложение маркетплейса, сборка, позиция сборки, заказ и позиция заказа. Служебные таблицы Laravel и Sanctum не входят в число предметных сущностей.

## Концептуальная модель

```mermaid
erDiagram
    USER ||--o{ BUILD : owns
    USER ||--o{ ORDER : places
    COMPONENT ||--o{ MARKETPLACE_OFFER : has
    BUILD ||--o{ BUILD_ITEM : contains
    COMPONENT ||--o{ BUILD_ITEM : selected
    BUILD |o--o{ ORDER : source
    ORDER ||--|{ ORDER_ITEM : contains
    COMPONENT ||--o{ ORDER_ITEM : purchased
```

Пользователь может иметь много сборок и заказов. Состав сборки реализует связь многие ко многим между сборками и компонентами через `build_items`. Один компонент допускается в сборке одной строкой, количество хранится в этой строке. У заказа один покупатель; исходную сборку можно впоследствии удалить, история заказа сохраняется.

## Физическая модель

Все идентификаторы — `BIGINT UNSIGNED`, первичный ключ `id`. Каждая предметная таблица имеет `created_at` и `updated_at`. Денежные значения — целые числа в минимальных единицах KZT (тиын), **100 тиын = 1 тенге**. Для суммы используются `BIGINT UNSIGNED`, арифметика PHP выполняется целыми числами. Клиент отправляет и получает такие же целые значения, без округления `float`.

| Таблица | Поля, помимо id и времени | Ограничения и индексы |
| --- | --- | --- |
| `users` | `name VARCHAR(255)`, `email VARCHAR(255)`, `password VARCHAR(255)`, `role VARCHAR(20) DEFAULT buyer`; служебные поля Laravel | Уникальный email; индекс role; допустимые роли API — buyer, admin |
| `components` | `name VARCHAR(255)`, `type ENUM(case,pcb,plate,stabilizer,switch,keycap)`, `price BIGINT UNSIGNED`, `stock INT UNSIGNED DEFAULT 0`, `format VARCHAR(20) NULL`, `specs JSON` | Индексы type, format, price |
| `marketplace_offers` | `component_id`, `marketplace VARCHAR(255)`, `url VARCHAR(2048)`, `price BIGINT UNSIGNED`, `price_updated_at DATETIME` | Внешний ключ component_id; HTTPS и дата проверяются API |
| `builds` | `user_id`, `name VARCHAR(255)`, `budget BIGINT UNSIGNED` | Внешний ключ user_id |
| `build_items` | `build_id`, `component_id`, `quantity INT UNSIGNED` | Уникальная пара build_id/component_id; оба поля — внешние ключи |
| `orders` | `user_id`, `build_id NULL`, `status VARCHAR(20) DEFAULT new`, `total BIGINT UNSIGNED`, `idempotency_key VARCHAR(100)`, `request_hash CHAR(64)` | Уникальная пара user_id/idempotency_key; индекс status; внешние ключи user_id/build_id |
| `order_items` | `order_id`, `component_id`, `quantity INT UNSIGNED`, `unit_price BIGINT UNSIGNED` | Внешние ключи order_id/component_id; unit_price фиксируется при оформлении |

`quantity` проверяется как положительное целое Form Request и сервисами. Статусы заказа определяет `OrderStatus`: `new → confirmed → assembled → issued`; отменить можно `new` или `confirmed`. Роль исключена из массового заполнения User: регистрация не может назначить администратора.

## Политика удаления

| Ссылка | Действие при удалении родителя | Причина |
| --- | --- | --- |
| builds.user_id → users.id | CASCADE | Сборки принадлежат пользователю |
| orders.user_id → users.id | RESTRICT | История покупок должна сохраняться |
| build_items.build_id → builds.id | CASCADE | Позиция сборки отдельно не существует |
| build_items.component_id → components.id | RESTRICT | Используемый компонент нельзя удалить |
| marketplace_offers.component_id → components.id | RESTRICT | CatalogService удаляет предложения перед удалением неиспользуемого компонента в той же транзакции |
| orders.build_id → builds.id | SET NULL | Удаление сборки не удаляет заказ |
| order_items.order_id → orders.id | CASCADE | Позиция заказа отдельно не существует |
| order_items.component_id → components.id | RESTRICT | Купленный компонент нельзя удалить |

Операции заказа и отмены выполняются транзакциями. Уникальность ключа повтора обеспечивается БД отдельно для каждого покупателя. Цены в `order_items` не меняются после изменения каталога. Связанные строки остатков блокируются в согласованном порядке по ID, чтобы конкурирующие заказы не продавали один остаток дважды.

## Контракт характеристик компонентов

`format` — строковый код формата, например `65` или `75`. Для корпуса, PCB и пластины он обязателен для успешной проверки совместимости; все три должны совпадать. Отсутствующие или некорректные характеристики попадают в `unverified` и запрещают оформление.

| Тип | Обязательные характеристики `specs` | Смысл |
| --- | --- | --- |
| case | `mount: string` | Тип крепления, например gasket |
| pcb | `mount: string`, `switch_type: string`, `layout: string`, `key_count: int`, `stabilizers: object` | Тип крепления и свитчей, раскладка, число клавиш и требуемые стабилизаторы |
| plate | `mount: string`, `switch_type: string` | Совпадает с корпусом/PCB по креплению, с PCB/свитчами по типу |
| stabilizer | `stabilizers: object` | Доступное количество стабилизаторов каждого размера |
| switch | `switch_type: string`, `stem: string` | Тип свитча и посадочного креста |
| keycap | `stem: string`, `layout: string`, `key_count: int` | Крепление, поддерживаемая раскладка и покрываемое число клавиш |

Пример PCB для набора 65%:

```json
{
  "format": "65",
  "specs": {
    "mount": "gasket",
    "switch_type": "mx",
    "layout": "ansi",
    "key_count": 67,
    "stabilizers": {"2u": 3, "6.25u": 1}
  }
}
```

Сборка содержит одну позицию каждого типа. Корпус, PCB, пластина, комплект стабилизаторов и комплект кейкапов имеют количество 1; количество свитчей не меньше числа клавиш PCB. Покрытие стабилизаторов проверяется для каждого требуемого размера. Кейкапы должны совпадать по stem и layout и покрывать число клавиш.

Эта модель совместимости проверяет явно внесённые характеристики. Она не выводит совместимость из названия товара и не заменяет проверку размеров и спецификаций производителем: для учебного каталога такие детали задаются администратором в согласованных полях.

## Демонстрационные данные

Seeder создаёт два комплекта по шесть компонентов: `KeyCraft 65 ...` (67 клавиш) и `KeyCraft 75 ...` (82 клавиши). Цены и остатки демонстрационные, не являются актуальными предложениями продавца.

| Тип | Цена, тиын | Цена, тенге | Начальный остаток |
| --- | ---: | ---: | ---: |
| case | 1800000 | 18000 | 20 |
| pcb | 1400000 | 14000 | 20 |
| plate | 450000 | 4500 | 20 |
| stabilizer | 300000 | 3000 | 20 |
| switch, за штуку | 18000 | 180 | 1000 |
| keycap | 900000 | 9000 | 20 |

Стоимость набора 65% — 6056000 тиын (60560 тенге), набора 75% — 6326000 тиын (63260 тенге). Бюджет демо сборки — 10000000 тиын. Предложения AliExpress помечены «поиск» и ведут на поиск типа комплектующих, а не на подтверждённую карточку продажи. Перед использованием с настоящими товарами администратор должен заменить ссылки, цены и характеристики.

По умолчанию пользователи не создаются. Для локального показа задайте `DEMO_ADMIN_PASSWORD` и/или `DEMO_BUYER_PASSWORD` длиной от 8 символов перед `php artisan db:seed`. Будут созданы `admin@keycraft.test` и/или `buyer@keycraft.test`. Для покупателя дополнительно создаются две полные сборки. Пароли не выводятся и не сохраняются в исходном коде; хешируются моделью User. Повторный запуск seeder не перезаписывает существующие цены/остатки и пароли.

Для наполнения по КП5 отдельно запустите `php artisan db:seed --class=CourseworkSeeder` с заданным `DEMO_BUYER_PASSWORD`. Он создаёт пять покупателей (`buyer@keycraft.test`, `buyer2@keycraft.test` … `buyer5@keycraft.test`), шесть полных сборок и пять заказов с шестью позициями каждый. Если задан `DEMO_ADMIN_PASSWORD`, также создаётся администратор. Заказы проходят через настоящий `OrderService`: остатки списываются в транзакциях, один заказ отменяется с возвратом товара. Показаны все пять статусов: new, confirmed, assembled, issued, cancelled. Повторный запуск не создаёт дубликаты и не списывает остатки повторно.

Для каждой сущности создана Factory. Factory предназначены для подготовки отдельных записей в тестах и позволяют явно задавать связи/атрибуты через `state` и `for`; полную согласованную бизнес-ситуацию создаёт `CourseworkSeeder`. `UserRole` и `OrderStatus` фиксируют словари ролей и статусов в Enum. Значение User.role остаётся строкой для проверки middleware; новый пользователь получает значение buyer по умолчанию БД.

## Миграции и воспроизводимость

```text
php artisan migrate
php artisan db:seed
```

Миграция предметной схемы — `2026_11_08_000001_create_keycraft_tables.php`. Откат удаляет таблицы в обратном порядке зависимостей и поле роли. `migrate:fresh` уничтожает существующие данные и предназначен только для отдельной тестовой БД.

## Экспорт физической схемы MySQL

`database/schema/mysql.sql` — экспорт DDL фактически созданной MySQL схемы: семь предметных таблиц и `personal_access_tokens` Sanctum. Данных пользователей, паролей, токенов, заказов и секретов подключения в файле нет. Экспорт не включает служебную таблицу `migrations`, поэтому штатное развёртывание приложения выполняется Laravel миграциями, а SQL служит для просмотра и отдельной проверки физической модели.

08.11.2026 файл успешно импортирован в новую отдельную БД `keycraft_schema_check`: MySQL показал 8 таблиц и 8 внешних ключей. Рабочие таблицы при этой проверке не изменялись. SQL содержит команды `DROP TABLE IF EXISTS`, поэтому повторный импорт допустим только в выделенную проверочную БД без нужных данных.

## Примеры JOIN-запросов

Ниже определения пяти запросов для изучения связей. Значения результатов зависят от содержимого БД; эти примеры не являются отчётом об исполнении. Для запросов к сборкам/заказам задайте `@user_id` идентификатором нужного покупателя. В приложении проверка владения выполняется серверными policies и фильтрами, а клиент не получает прямой доступ к SQL.

1. Каталог с числом предложений и минимальной внешней ценой. `LEFT JOIN` сохраняет компоненты без предложений.

```sql
SELECT c.id, c.name, c.type, c.price AS own_price,
       COUNT(mo.id) AS offer_count, MIN(mo.price) AS lowest_offer_price
FROM components AS c
LEFT JOIN marketplace_offers AS mo ON mo.component_id = c.id
GROUP BY c.id, c.name, c.type, c.price
ORDER BY c.id;
```

2. Подробный состав сборок выбранного покупателя с именем владельца.

```sql
SET @user_id = 1; -- Замените на ID покупателя.
SELECT b.id AS build_id, b.name AS build_name, u.name AS owner_name,
       c.id AS component_id, c.name AS component_name, c.type,
       bi.quantity, c.price AS unit_price,
       bi.quantity * c.price AS line_total
FROM builds AS b
JOIN users AS u ON u.id = b.user_id
JOIN build_items AS bi ON bi.build_id = b.id
JOIN components AS c ON c.id = bi.component_id
WHERE b.user_id = @user_id
ORDER BY b.id, bi.id;
```

3. Актуальная стоимость сборок и отклонение от бюджета. Пустая сборка имеет нулевую сумму. Это только расчёт цены: он не заменяет проверку совместимости и остатков.

```sql
SELECT b.id, b.name, b.budget,
       COALESCE(SUM(bi.quantity * c.price), 0) AS current_total,
       CAST(b.budget AS SIGNED) - CAST(COALESCE(SUM(bi.quantity * c.price), 0) AS SIGNED)
           AS budget_remaining
FROM builds AS b
LEFT JOIN build_items AS bi ON bi.build_id = b.id
LEFT JOIN components AS c ON c.id = bi.component_id
WHERE b.user_id = @user_id
GROUP BY b.id, b.name, b.budget
ORDER BY b.id;
```

4. История позиций заказов покупателя. Сумма позиции рассчитывается по исторической `unit_price`, а не по текущей цене каталога.

```sql
SELECT o.id AS order_id, o.status, o.created_at, u.name AS buyer_name,
       c.name AS component_name, oi.quantity, oi.unit_price,
       oi.quantity * oi.unit_price AS purchased_line_total
FROM orders AS o
JOIN users AS u ON u.id = o.user_id
JOIN order_items AS oi ON oi.order_id = o.id
JOIN components AS c ON c.id = oi.component_id
WHERE o.user_id = @user_id
ORDER BY o.id DESC, oi.id;
```

5. Популярность компонентов и выручка по выданным заказам. Отменённые заказы не входят в продажи.

```sql
SELECT c.id, c.name, c.type,
       SUM(oi.quantity) AS issued_quantity,
       SUM(oi.quantity * oi.unit_price) AS issued_revenue
FROM components AS c
JOIN order_items AS oi ON oi.component_id = c.id
JOIN orders AS o ON o.id = oi.order_id
WHERE o.status = 'issued'
GROUP BY c.id, c.name, c.type
ORDER BY issued_quantity DESC, c.id;
```

Все денежные результаты этих запросов также выражены в тиынах KZT.
