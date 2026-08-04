# Changelog

All notable changes to this package are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.2.0] - 2026-08-05

### Added

- `InvalidAdjusterException::missingKey()`, `invalidValue()` and `malformedEntry()`, `InvalidLineException::invalidValue()` and `invalidPayload()` factories for payload validation errors.

### Fixed

- Built-in adjusters validate their payload in `fromArray()`: a malformed record from storage now throws a domain `InvalidAdjusterException` instead of a fatal `TypeError`; a garbage `percent` value no longer silently collapses to a zero discount. The `name` key of `Shipping` is optional and defaults to `shipping`, matching the constructor.
- `Line::fromArray()` validates value types, `Cart::fromArray()` validates the shape of the `lines` and `adjusters` sections and of every adjuster entry: same domain exceptions instead of `TypeError`.
- `PercentageFee` is covered by tests, including the serialization roundtrip.

## [2.1.0] - 2026-08-04

### Added

- Memoized model resolution: repeated `Line::model()` calls on the same line hit the database once; the result (including a miss) is remembered in a `WeakMap` for the lifetime of the line instance.
- Batch hydration: `ManagedList::models()` loads models for every line with one `findMany()` per purchasable type, primes the memoization and returns a collection keyed by line id.

## [2.0.0] - 2026-08-04

### Added

- Cart identity layer: `CartIdentity` contract, `IdentityManager` with `extend()`, `session` (default) and `cookie` drivers; the cookie is queued lazily on the first real write, so an empty visitor never receives one.
- `AuthAwareIdentity` owner resolution shared by the database driver and the cache layer: authenticated users are keyed by auth id, guests by the identity driver.
- Write-through cache layer (`cache.enabled`) wrapped around any storage driver, including custom ones; one cache key holds every list of a cart, so warm reads make zero database queries.
- `SupportsOwnerMerge` contract gating the guest-cart merge listener; custom drivers without merge support are skipped silently on login.
- `SupportsOwnerScope` contract with `DatabaseStorage::forOwner()` for writing on behalf of an explicit owner id (bulk imports).

### Changed

- The package is renamed from `timurturdyev/laravel-cart` to `timurturdyev/simple-cart`; the root namespace changes from `TimurTurdyev\Cart` to `TimurTurdyev\SimpleCart`. The old Packagist package is abandoned in favor of the new one.
- The whole naming surface follows the package name: config file and key `cart` → `simple_cart`, publish tags `cart-config`/`cart-migrations` → `simple-cart-config`/`simple-cart-migrations`, table `cart_lists` → `simple_cart_lists`, cookie default `cart_id` → `simple_cart_id`, env `CART_COOKIE` → `SIMPLE_CART_COOKIE`, cache prefix `cart_` → `simple_cart_`.
- `ManagedList::withoutAdjuster()` is renamed to `removeAdjuster()`: the method mutates the list, so the name no longer reads as an immutable wither. The core `Cart::withoutAdjuster()` keeps its name and immutable semantics.
- `DatabaseStorage` is constructed with a `CartIdentity` instead of an owner `Closure` (affects direct instantiation only; `StorageManager` wiring is unchanged).
- The `simple_cart_lists` payload format is documented as a public contract: external applications may read the table directly, and the structure only changes in major versions.
- `illuminate/auth` is declared as a direct dependency (the `Login` event was used undeclared before).
- Composer platform is pinned to PHP 8.3 so dev dependencies always resolve against the minimum supported version.

### Fixed

- `DatabaseStorage::mergeOwners()` runs in a transaction and deletes guest records one by one after each list is merged; a failure mid-merge no longer leaves half-merged state, and re-running does not double quantities. Guest lists without a configured policy stay untouched instead of being deleted.
- Merging a guest cart into a full list no longer throws `ListLimitException` out of the login listener: lines over the limit are skipped.
- `CookieIdentity` accepts only its own 32-hex-character values; a forged cookie carrying someone else's auth id can no longer read that owner's cart.
- `Line::model()` returns null for classes without a static `query()` instead of a fatal error; `Line::fromArray()` validates required payload keys and throws `InvalidLineException` on malformed data.
- `Cart::setQuantity()` keeps the line position instead of moving it to the end; a repeated `add()` in a toggle list no longer writes to storage or dispatches a false `LineAdded` event.

### Removed

- The `cart:import-legacy` command and the public `LegacyImporter` class. Legacy data migration is an application-side script now: build the payload and write it for an explicit owner via `SupportsOwnerScope::forOwner()` (see UPGRADE.md).

## [1.0.0] - 2026-07-30

### Added

- Item list core: `Line` with hash-based identity (purchasable id + class + normalized options), `meta` slot for non-identity data, lazy `model()` resolution.
- List policies from config: `append` and `toggle` modes, optional `limit`; any number of named lists (`cart`, `wishlist`, `compare`, custom).
- Money in integer minor units via the `Price` value object with a single half-up conversion point; `Totals` with a per-adjustment breakdown.
- Calculation pipeline: `Adjuster` contract plus `PercentageDiscount`, `FixedDiscount`, `PercentageFee` and `Shipping`; serialization as `{class, data}` with strict contract validation on restore.
- `CartManager` with per-list instances, `Cart`/`Wishlist`/`Compare` facades, `moveTo`/`moveToCart` bridges.
- Storage drivers: session (default, lazy record creation) and database (publishable migration, `CartRecord` model); custom drivers via `StorageManager::extend()`.
- Guest cart merge on login with `sum`/`keep`/`replace` strategies.
- Lifecycle events: `LineAdded`, `LineUpdated`, `LineRemoved`, `ListCleared`.
- `cart:import-legacy` command and the public `LegacyImporter` for converting darryldecode/laravelshoppingcart data.
