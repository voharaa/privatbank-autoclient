# PHP-клієнт для API «Автоклієнт» ПриватБанку

[English](README.md) · **Українська**

> **Неофіційний пакет.** ПриватБанк не створював, не схвалював і не підтримує цей пакет.

PHP-клієнт для API **«Автоклієнт»** ПриватБанку («Приват24 для бізнесу»): баланси й транзакції за рахунками.
Зроблено за [документацією API банку](https://docs.google.com/document/d/e/2PACX-1vTtKvGa3P4E-lDqLg3bHRF6Wi9S7GIjSMFEFxII5qQZBGxuTXs25hQNiUU1hMZQhOyx6BNvIZ1bVKSr/pub) **версії 3.0.0**.

- Працює з будь-яким PSR-18 HTTP-клієнтом (Guzzle, Symfony HttpClient тощо)
- Типізовані об'єкти `Balance` і `Transaction` зі зрозумілими назвами полів
- Пагінацію пакет бере на себе, сторінки завантажуються ліниво

## Вимоги

- PHP 8.3 або новіший
- Токен API «Автоклієнта»: «Приват24 для бізнесу» → «Інтеграція (Автоклієнт)» → «Підключити API».
  Для юридичних осіб API доступний у тарифах «Бізнес Комфорт» і «Бізнес ПРО», для ФОП — у будь-якому тарифі.

## Встановлення

Встановіть пакет через [Composer](https://getcomposer.org):

```bash
composer require voharaa/privatbank-autoclient
```

Пакету потрібен PSR-18 HTTP-клієнт. Дозвольте Composer-плагіну `php-http/discovery` працювати (він поставить
клієнт сам) або встановіть клієнт вручну, якщо у вашому проєкті його ще немає:

```bash
composer require guzzlehttp/guzzle
```

Підійде й будь-який інший PSR-18 клієнт із PSR-17 фабриками, наприклад Symfony HttpClient.

### Laravel

Laravel уже містить Guzzle, тому пакет працює одразу після `composer require voharaa/privatbank-autoclient`.
Сервіс-провайдера й фасаду поки немає, тож зареєструйте клієнт самі:

```php
// config/services.php
'privatbank' => [
    'token' => env('PRIVATBANK_TOKEN'),
],

// app/Providers/AppServiceProvider.php
use Voharaa\PrivatbankAutoclient\Client;

public function register(): void
{
    $this->app->singleton(Client::class, fn () => Client::make(config('services.privatbank.token')));
}
```

Далі просто впроваджуйте `Client` туди, де він потрібен. Фасад `Http` у Laravel не є PSR-18 клієнтом, тому
пакет працює з Guzzle напряму, і `Http::fake()` на нього не впливає.

## Швидкий старт

```php
use Voharaa\PrivatbankAutoclient\Client;

$client = Client::make(getenv('PRIVATBANK_TOKEN'));

$transactions = $client->statements()->transactions(new DateTimeImmutable('-7 days'));

foreach ($transactions as $tx) {
    echo $tx->valueDate, ' ', $tx->isCredit() ? '+' : '-', $tx->amount, ' ', $tx->currency, ' ', $tx->purpose, PHP_EOL;
}
```

> ⚠️ **Кожен `foreach` по курсору знову надсилає запити.** Дані завантажуються сторінка за сторінкою під час
> ітерації й ніде не кешуються. Якщо дані потрібні більше одного разу, викличте `->all()` один раз і працюйте
> з масивом. Банк рекомендує робити не більше одного запиту на секунду.

## Використання

### Налаштування

```php
$settings = $client->statements()->settings();

$settings->isApiAvailable(); // false під час регламентних робіт банку
$settings->today;            // "30.03.2020 00:00:00"
```

### Баланси

```php
$balances = $client->statements()->balances(
    startDate: new DateTimeImmutable('2026-11-01'),
    endDate: new DateTimeImmutable('2026-11-15'), // необов'язково
    account: 'UA943052990000026100050001037',     // необов'язково; без нього — усі активні рахунки
);

foreach ($balances as $balance) {
    echo $balance->date, ' ', $balance->account, ': ', $balance->closingBalance, ' ', $balance->currency, PHP_EOL;
}
```

За інтервал дат API повертає **один рядок на кожен рахунок за кожен день**, тож 10 рахунків за 30 днів — це приблизно
300 рядків. `$balance->date` показує, за який день рядок. Щоб отримати лише сьогоднішні баланси, передайте сьогоднішню
дату як `startDate`:

```php
$today = $client->statements()->balances(new DateTimeImmutable('today'))->all(); // один рядок на рахунок
```

### Транзакції

```php
$transactions = $client->statements()->transactions(new DateTimeImmutable('2026-11-01'))->all();

foreach ($transactions as $tx) {
    $tx->id;                   // унікальний для кожного рядка, використовуйте для дедуплікації
    $tx->instructionKey();     // REF + REFN — платіжна інструкція, див. нижче
    $tx->direction;            // TransactionDirection::Debit або ::Credit
    $tx->status;               // TransactionStatus::Posted, ::Processing, ::Reversed, ::Rejected
    $tx->counterpartyName;
    $tx->counterpartyEdrpou;
}
```

Поля `own*` описують ваш рахунок (`AUT_MY_*` в API), а `counterparty*` — іншу сторону (`AUT_CNTR_*`).

Переказ між двома вашими рахунками з'являється у виписці **двічі**: як дебет на одному рахунку і як кредит на іншому.
В обох рядків однаковий `instructionKey()`, але різні `id`.

### Сирі дані API

Усі об’єкти відповідей (`Transaction`, `Balance`, `Settings`, `ExchangeRates`, …) зберігають рядок рівно в тому вигляді, як його повернув API, у `$raw`,
разом із полями, для яких немає окремої властивості. Нічого не перетворюється: порожні рядки лишаються `""`,
тоді як відповідна властивість дорівнює `null`.

```php
$tx->raw;               // ['ID' => '557091731', 'REF' => 'DNCHK0108B1WKX', 'SUM_E' => '0.01', ...]
$tx->raw['TRANTYPE'];   // "C"

json_encode($tx->raw);  // наприклад, щоб зберегти оригінальний рядок у БД
```

Усі вони реалізують `HasRawResponse`, тож будь-який об'єкт відповіді можна обробляти однаково:

```php
use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;

function storeRaw(HasRawResponse $response): void
{
    DB::table('bank_rows')->insert(['payload' => json_encode($response->raw())]);
}
```

### Проміжна й підсумкова виписка

```php
$client->statements()->interimBalances();     // з останнього операційного дня до сьогодні
$client->statements()->finalBalances();       // за останній закритий день
$client->statements()->interimTransactions();
$client->statements()->finalTransactions();
```

### Курси валют

```php
$rates = $client->currency()->rates();

$usd = $rates->get('USD');  // null, якщо API не повернув цю валюту
$usd->buy->rate;            // "26.0700000", курс купівлі банком
$usd->sell->rate;           // "26.3200000", курс продажу банком
$usd->buy->delta;           // "-0.0600000", зміна курсу
$usd->buy->nbuRate;         // "26.1888140", курс НБУ
$usd->buy->date;            // "18-05-2018 14:00:01"

$usd->rate(CurrencyRateType::Sell); // те саме, що $usd->sell

$rates->currencies();       // ['USD', 'EUR', ...]
$rates->fromCache;          // true, якщо API віддав дані з кешу
$rates->cacheTime;          // час кешування, Unix-час у мілісекундах; null, якщо дані не з кешу

// Історія за період не довший за 15 днів (перевіряється до надсилання запиту)
$history = $client->currency()->history(new DateTimeImmutable('-7 days'), new DateTimeImmutable('today'));

foreach ($history->forCurrency('USD') as $day) {
    echo $day->date, ': ', $day->buy, ' / ', $day->sell, ' (НБУ ', $day->nbuRate, ')', PHP_EOL;
}

$history->rates;            // list<HistoricalRate>: рядок на кожну валюту за кожен день, від найстарішого
$history->dates();          // ['11-11-2026', '12-11-2026', ...], дні без курсів (вихідні) пропущені
$history->forDate(new DateTimeImmutable('2026-11-17')); // усі валюти за один день
$history->currencies();     // ['CHF', 'EUR', 'USD', 'PLZ', ...]
```

Історія має іншу структуру, ніж поточні курси: кожен `HistoricalRate` містить обидві сторони
(`buy`, `sell`, `buyDelta`, `sellDelta`, `nbuRate`) за один день, а `rate()` / `delta()` приймають `CurrencyRateType`.
Коди валют лишаються такими, як їх повертає API, і не завжди відповідають ISO 4217 (для злотого банк надсилає `PLZ`).

> **Примітка:** реальна відповідь `proxy/currency/history` відрізняється від документації банку, де показано ту саму
> структуру, що й для `proxy/currency`. Насправді API повертає `data.history`: список рядків із `date`, `currencyCode`,
> `nbuRate`, `rate_b`, `rate_b_delta`, `rate_s` і `rate_s_delta`. Бібліотека розбирає саме цей реальний формат (перевірено 17.11.2026).

`ExchangeRates`, `ExchangeRatesHistory` і вкладені в них об’єкти реалізують `HasRawResponse`.

### Особливості даних

- Суми — це рядки (`"250.75"`), щоб не втрачати точність. Для арифметики використовуйте `bcmath` або бібліотеку для роботи з грошима.
- Дати — рядки саме в тому вигляді, як їх повертає API, за київським часом: `"d.m.Y H:i:s"` або `"d.m.Y"`.
- `Balance::$closedAt` дорівнює `null` для незакритих рахунків.
- `Balance::$isFinal` дорівнює `false` для днів, яких ще немає в підсумковій виписці (останній операційний день і сьогодні).
- Деякі поля з документації банку приходять не завжди (наприклад, `Transaction::$technicalId` зазвичай `null`).

## Обробка помилок

```php
use Voharaa\PrivatbankAutoclient\Exceptions\ApiException;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;

try {
    $balances = $client->statements()->balances(new DateTimeImmutable('-1 day'))->all();
} catch (ApiException $e) {
    // API відповів помилкою: неправильний токен (401), вимкнений обліковий запис (403), status !== "SUCCESS", 5xx…
    $e->statusCode;  // HTTP-статус, те саме, що $e->getCode()
    $e->errorCode;   // код помилки API, наприклад "VE005"
    $e->serviceCode; // наприклад "PMTSRV0112"
    $e->requestId;   // вкажіть його, коли звертаєтеся до підтримки банку
} catch (TransportException $e) {
    // Немає придатної відповіді: помилка мережі, некоректний JSON, неочікуваний формат відповіді
}
```

Курсори ліниві, тому ці винятки виникають під час ітерації (або в `->all()`), а не під час виклику `balances()`.

## Підтримані ендпоінти

| Ендпоінт | Метод |
|---|---|
| `statements/settings` | `settings()` |
| `statements/balance` | `balances()` |
| `statements/balance/interim` | `interimBalances()` |
| `statements/balance/final` | `finalBalances()` |
| `statements/transactions` | `transactions()` |
| `statements/transactions/interim` | `interimTransactions()` |
| `statements/transactions/final` | `finalTransactions()` |
| `proxy/currency` | `currency()->rates()` |
| `proxy/currency/history` | `currency()->history()` |

### Поки не підтримується

- Режим групи ПП (`?id=`)
- Платежі: створення, підписання, видалення, SWIFT та інші валютні платежі
- Електронний документообіг (ЕДО, рахунки-фактури, податкові накладні)
- Електронна звітність
- Зарплатний проєкт і розрахункові листи
- Корпоративні картки
- Перевірка контрагентів, квитанції за платежами, еквайрингова виписка

## Безпека

Ніколи не комітьте свій токен. Він дає доступ до ваших рахунків на читання, а якщо доступ не обмежено
в налаштуваннях застосунку, то й змогу створювати платежі. У «Приват24 для бізнесу» токен можна обмежити
лише сервісом «Отримання балансів і транзакцій за рахунками» на вкладці «Обмеження по сервісам».

## Розробка

```bash
composer test     # Pest, мережа не потрібна
composer analyse  # PHPStan, рівень max
composer lint     # Pint
```

## Ліцензія

MIT, див. [LICENSE](LICENSE).
