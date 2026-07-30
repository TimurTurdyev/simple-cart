# Changelog

All notable changes to this package are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
