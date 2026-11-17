# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - Unreleased

First public release.

### Added

- `Client::make($token)` with automatic discovery of any PSR-18 HTTP client.
- `statements()->settings()`: server dates and API availability (`isApiAvailable()`).
- `statements()->balances()` and `statements()->transactions()` for a date range,
  plus `interim*()` and `final*()` variants.
- Lazy, re-iterable `Cursor` that follows `followId` pagination, with `all()`.
- Typed `Balance` and `Transaction` objects with readable property names.
- `Balance::$date`: the day a balance row is for (the API returns one row per account per day).
- `Transaction::instructionKey()` (REF + REFN) and `Transaction::$id` for telling rows apart.
- `$raw` on `Transaction`, `Balance` and `Settings`: the row exactly as returned by the API,
  including fields not mapped to properties. Also available as `raw()` through the `HasRawResponse` interface.
- `currency()->rates()` and `currency()->history()` for PrivatBank exchange rates (buy, sell, NBU rate),
  with typed `ExchangeRates`, `CurrencyRate` and `Rate` objects and the `CurrencyRateType` enum (buy/sell). `history()` rejects periods longer than 15 days
  before sending the request.
- `ApiException` (HTTP status, API error code, service code, request ID) and
  `TransportException` (network errors, invalid JSON, unexpected response format).
- Protection against endless pagination when the API returns a missing or repeated `next_page_id`.

[0.1.0]: https://github.com/voharaa/privatbank-autoclient/releases/tag/v0.1.0
