# Changelog

All notable changes to this package are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Cart identity layer: `CartIdentity` contract, `IdentityManager` with `extend()`, `session` (default) and `cookie` drivers; the cookie is queued lazily on the first real write, so an empty visitor never receives one.
- `AuthAwareIdentity` owner resolution shared by the database driver and the cache layer: authenticated users are keyed by auth id, guests by the identity driver.
- Write-through cache layer (`cache.enabled`) wrapped around any storage driver, including custom ones; one cache key holds every list of a cart, so warm reads make zero database queries.
- `SupportsOwnerMerge` contract gating the guest-cart merge listener; custom drivers without merge support are skipped silently on login.
- `SupportsOwnerScope` contract with `DatabaseStorage::forOwner()` for writing on behalf of an explicit owner id (bulk imports).

### Changed

- `DatabaseStorage` is constructed with a `CartIdentity` instead of an owner `Closure` (affects direct instantiation only; `StorageManager` wiring is unchanged).
- The `cart_lists` payload format is documented as a public contract: external applications may read the table directly, and the structure only changes in major versions.
- `illuminate/auth` is declared as a direct dependency (the `Login` event was used undeclared before).
- Composer platform is pinned to PHP 8.3 so dev dependencies always resolve against the minimum supported version.

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
