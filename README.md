# PrivatBank Autoclient PHP client

**English** · [Українська](README.uk.md)

> **Unofficial.** This package is not made, endorsed or supported by PrivatBank.

PHP client for the PrivatBank **Autoclient** API (Privat24 for Business): account balances and transactions.
Built against the bank's [API documentation](https://docs.google.com/document/d/e/2PACX-1vTtKvGa3P4E-lDqLg3bHRF6Wi9S7GIjSMFEFxII5qQZBGxuTXs25hQNiUU1hMZQhOyx6BNvIZ1bVKSr/pub) **version 3.0.0**.

- Works with any PSR-18 HTTP client (Guzzle, Symfony HttpClient, …)
- Typed `Balance` and `Transaction` objects with readable property names
- Pagination handled for you, lazily

## Requirements

- PHP 8.3 or newer
- An Autoclient API token: Privat24 for Business → «Інтеграція (Автоклієнт)» → «Підключити API».
  For legal entities the API is available on the «Бізнес Комфорт» and «Бізнес ПРО» plans, for sole proprietors (ФОП) on any plan.

## Installation

Install the package via [Composer](https://getcomposer.org):

```bash
composer require voharaa/privatbank-autoclient
```

The package needs a PSR-18 HTTP client. Ensure that the `php-http/discovery` Composer plugin is allowed to run
(it installs one for you), or install a client manually if your project does not already have one:

```bash
composer require guzzlehttp/guzzle
```

Any other PSR-18 client with PSR-17 factories (for example Symfony HttpClient) works too.

### Laravel

Laravel already ships with Guzzle, so the package works right after `composer require voharaa/privatbank-autoclient`.
There is no service provider or facade yet; register the client yourself:

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

Then inject `Client` wherever you need it. Laravel's `Http` facade is not a PSR-18 client, so the package
uses Guzzle directly and `Http::fake()` does not affect it.

## Quick start

```php
use Voharaa\PrivatbankAutoclient\Client;

$client = Client::make(getenv('PRIVATBANK_TOKEN'));

$transactions = $client->statements()->transactions(new DateTimeImmutable('-7 days'));

foreach ($transactions as $tx) {
    echo $tx->valueDate, ' ', $tx->isCredit() ? '+' : '-', $tx->amount, ' ', $tx->currency, ' ', $tx->purpose, PHP_EOL;
}
```

> ⚠️ **Every `foreach` over a cursor sends the requests again.** Results are fetched page by page while you iterate,
> nothing is cached. If you need the data more than once, call `->all()` once and work with the array.
> The bank recommends at most one request per second.

## Usage

### Settings

```php
$settings = $client->statements()->settings();

$settings->isApiAvailable(); // false during the bank's maintenance windows
$settings->today;            // "30.03.2020 00:00:00"
```

### Balances

```php
$balances = $client->statements()->balances(
    startDate: new DateTimeImmutable('2026-11-01'),
    endDate: new DateTimeImmutable('2026-11-15'), // optional
    account: 'UA943052990000026100050001037',     // optional, all active accounts if omitted
);

foreach ($balances as $balance) {
    echo $balance->date, ' ', $balance->account, ': ', $balance->closingBalance, ' ', $balance->currency, PHP_EOL;
}
```

For a date range the API returns **one row per account per day**, so 10 accounts over 30 days are about 300 rows.
`$balance->date` tells which day a row is for. For today's balances only, pass today as `startDate`:

```php
$today = $client->statements()->balances(new DateTimeImmutable('today'))->all(); // one row per account
```

### Transactions

```php
$transactions = $client->statements()->transactions(new DateTimeImmutable('2026-11-01'))->all();

foreach ($transactions as $tx) {
    $tx->id;                   // unique per row, use it to deduplicate
    $tx->instructionKey();     // REF + REFN: the payment instruction, see below
    $tx->direction;            // TransactionDirection::Debit or ::Credit
    $tx->status;               // TransactionStatus::Posted, ::Processing, ::Reversed, ::Rejected
    $tx->counterpartyName;
    $tx->counterpartyEdrpou;
}
```

`own*` properties describe your account (`AUT_MY_*` in the API), `counterparty*` the other side (`AUT_CNTR_*`).

A transfer between two of your own accounts appears **twice**: as a debit on one account and a credit on the other.
Both rows have the same `instructionKey()` but different `id`s.

### Raw API data

Every response object (`Transaction`, `Balance`, `Settings`, `ExchangeRates`, …) keeps the row exactly as the API returned it in `$raw`,
including fields that have no property of their own. Nothing is converted: empty strings stay `""`,
while the matching property is `null`.

```php
$tx->raw;               // ['ID' => '557091731', 'REF' => 'DNCHK0108B1WKX', 'SUM_E' => '0.01', ...]
$tx->raw['TRANTYPE'];   // "C"

json_encode($tx->raw);  // e.g. to store the original row in a database
```

All of them implement `HasRawResponse`, so you can handle any response object the same way:

```php
use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;

function storeRaw(HasRawResponse $response): void
{
    DB::table('bank_rows')->insert(['payload' => json_encode($response->raw())]);
}
```

### Interim and final statements

```php
$client->statements()->interimBalances();     // from the last operating day to today
$client->statements()->finalBalances();       // the last closed day
$client->statements()->interimTransactions();
$client->statements()->finalTransactions();
```

### Currency rates

```php
$rates = $client->currency()->rates();

$usd = $rates->get('USD');  // null if the API did not return this currency
$usd->buy->rate;            // "26.0700000", the bank buys USD at this rate
$usd->sell->rate;           // "26.3200000", the bank sells USD at this rate
$usd->buy->delta;           // "-0.0600000", change of the rate
$usd->buy->nbuRate;         // "26.1888140", official NBU rate
$usd->buy->date;            // "18-05-2018 14:00:01"

$usd->rate(CurrencyRateType::Sell); // same as $usd->sell

$rates->currencies();       // ['USD', 'EUR', ...]
$rates->fromCache;          // true if the API served cached data
$rates->cacheTime;          // Unix time in milliseconds

// History for a period of at most 15 days (checked before sending the request)
$history = $client->currency()->history(new DateTimeImmutable('-7 days'), new DateTimeImmutable('today'));
```

`ExchangeRates`, `CurrencyRate` and `Rate` also implement `HasRawResponse`.

### Notes on the data

- Amounts are strings (`"250.75"`) to keep their precision. Use `bcmath` or a money library for arithmetic.
- Dates are strings exactly as the API returns them, in local Kyiv time: `"d.m.Y H:i:s"` or `"d.m.Y"`.
- `Balance::$closedAt` is `null` for accounts that are not closed.
- `Balance::$isFinal` is `false` for days that are not yet in the final statement (the last operating day and today).
- Some fields described in the bank's docs are not always returned (e.g. `Transaction::$technicalId` is usually `null`).

## Error handling

```php
use Voharaa\PrivatbankAutoclient\Exceptions\ApiException;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;

try {
    $balances = $client->statements()->balances(new DateTimeImmutable('-1 day'))->all();
} catch (ApiException $e) {
    // The API answered with an error: wrong token (401), disabled account (403), status !== "SUCCESS", 5xx…
    $e->statusCode;  // HTTP status, also $e->getCode()
    $e->errorCode;   // API error code, e.g. "VE005"
    $e->serviceCode; // e.g. "PMTSRV0112"
    $e->requestId;   // quote it when contacting the bank's support
} catch (TransportException $e) {
    // No usable answer: network error, invalid JSON, unexpected response format
}
```

Because cursors are lazy, these exceptions are thrown while iterating (or in `->all()`), not when calling `balances()`.

## Supported endpoints

| Endpoint | Method |
|---|---|
| `statements/settings` | `settings()` |
| `statements/balance` | `balances()` |
| `statements/balance/interim` | `interimBalances()` |
| `statements/balance/final` | `finalBalances()` |
| `statements/transactions` | `transactions()` |
| `statements/transactions/interim` | `interimTransactions()` |
| `statements/transactions/final` | `finalTransactions()` |
| `proxy/currency/` | `currency()->rates()` |
| `proxy/currency/history` | `currency()->history()` |

### Not supported yet

- Group mode (`?id=` for groups of sole proprietors)
- Payments: creating, signing, deleting, SWIFT and other currency payments
- Electronic document management (EDO, invoices, tax invoices)
- Electronic reporting
- Salary project and payslips
- Corporate cards
- Counterparty checks, payment receipts, acquiring statements

## Security

Never commit your token. It gives read access to your accounts (and, if not restricted in the app settings,
the ability to create payments). In Privat24 for Business you can limit the token to
«Отримання балансів і транзакцій за рахунками» under «Обмеження по сервісам».

## Development

```bash
composer test     # Pest, no network access needed
composer analyse  # PHPStan, level max
composer lint     # Pint
```

## License

MIT, see [LICENSE](LICENSE).
