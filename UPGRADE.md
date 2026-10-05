# Upgrade Guide

## From 2.x to 3.0

```bash
php artisan migrate
```

The upgrade migration adds `version`, `status`, `status_changed_at`, `reference`, `slot` and changes the unique index to `(owner, list, slot)`. Old rows stay active. Try it on a copy of the database first.

Defaults are `storage = database` and `identity.driver = cookie`. Old behaviour:

```dotenv
CART_STORAGE=session
CART_IDENTITY=session
```

Other changes:

- `add()` quantity is `?int`; null means the rule minimum, or 1.
- On login the guest list is kept with `status = merged`. Count only `CartRecord::query()->active()`.
- After an order call `Cart::checkout($number)` instead of `clear()`.
- Readers of the table should filter `slot = ''`.
- Cookie ids must match `identity.cookie.pattern`; for `uniqid('cart', true)` use `'/^([0-9a-f]{32}|cart[0-9a-f]{13}\d\.\d{8})$/'`.
- Schedule `php artisan simple-cart:prune`.

## From 1.0 to 2.0

- The package is renamed: require `timurturdyev/simple-cart` instead of `timurturdyev/laravel-cart` and change the namespace prefix `TimurTurdyev\Cart` to `TimurTurdyev\SimpleCart` in your imports.
- The config follows: rename the published `config/cart.php` to `config/simple_cart.php` (the key is `simple_cart` now), re-run `vendor:publish` with the `simple-cart-config` / `simple-cart-migrations` tags, and rename the `cart_lists` table to `simple_cart_lists` (or republish the migration). The cookie default is `simple_cart_id`, its env name is `SIMPLE_CART_COOKIE`.
- `ManagedList::withoutAdjuster()` (the facade-level call) is now `removeAdjuster()`; the immutable `Cart::withoutAdjuster()` in the core keeps its name.
- `Storage\DatabaseStorage` is now constructed with a `Contracts\CartIdentity` instead of an owner `Closure`. The driver is wired by `StorageManager`, so this only affects code instantiating `DatabaseStorage` directly - wrap your owner resolution into a `CartIdentity` (see `Identity\FixedIdentity`).
- The `cart:import-legacy` command and the public `Legacy\LegacyImporter` class are removed. Data migration is an application-side one-off script now: build the new payload and write it for an explicit owner via `forOwner()` (see [Data migration](#data-migration) below).
- New config sections `identity` and `cache` ship with backwards-compatible defaults (`identity.driver=session`, `cache.enabled=false`); published configs keep working without changes.

## From darryldecode/laravelshoppingcart

This package is a clean rewrite, not a drop-in replacement. The ideas differ in three places:

- **Line identity.** The row id is a hash of the purchasable id, its class and the options. You no longer invent composite ids like `11-color-2` - pass the variant set as `options` and the package keeps lines apart.
- **Money.** Amounts are integer minor units inside the `Price` value object; floats never leak into the math.
- **Adjusters instead of conditions.** String values like `'-10%'` are gone. Discounts, fees and shipping are typed classes applied as a pipeline.

### API mapping

| darryldecode | simple-cart |
|---|---|
| `Cart::add(['id' => ..., 'name' => ..., 'price' => ..., 'quantity' => ..., 'attributes' => ...])` | `Cart::add($product, quantity: 2, options: [...])` or `Cart::add(Line::of(...))` |
| `Cart::update($id, ['quantity' => ['relative' => false, 'value' => 5]])` | `Cart::setQuantity($lineId, 5)` |
| `Cart::update($id, ['quantity' => 2])` (relative) | `Cart::changeQuantity($lineId, 2)` |
| `Cart::get($id)` | `Cart::get($lineId)` |
| `Cart::has($id)` | `Cart::has($lineId)` / `Cart::has($product, options: [...])` |
| `Cart::remove($id)` | `Cart::remove($lineId)` |
| `Cart::getContent()` | `Cart::items()` |
| `Cart::getTotalQuantity()` | `Cart::totalQuantity()` |
| `Cart::getSubTotal()` | `Cart::subtotal()->minor()` / `->decimal()` |
| `Cart::getTotal()` | `Cart::total()->minor()` / `->decimal()` |
| `Cart::isEmpty()` | `Cart::isEmpty()` |
| `Cart::clear()` | `Cart::clear()` |
| `Cart::condition(new CartCondition([... 'value' => '-10%']))` | `Cart::adjust(new PercentageDiscount('sale', 10))` |
| `Cart::removeCartCondition($name)` | `Cart::removeAdjuster($name)` |
| `Cart::getConditions()` | `Cart::adjusters()` / `Cart::totals()->breakdown()` |
| `Cart::instance('wishlist')` | `Wishlist::` facade or `app(CartManager::class)->list('wishlist')` |
| `$item->attributes['color']` | `$line->option('color')` (identity) or `$line->meta('color')` (metadata) |
| `$item->attributes->all()` | `$line->options` / `$line->meta` |
| `$item->model` | `$line->model()` |
| `$item->getPriceSum()` | `$line->subtotal()->minor()` |
| custom storage (`get`/`has`/`put`) | implement `Contracts\Storage` (`read`/`write`/`forget`), register via `StorageManager::extend()` |

### Condition value mapping

| Legacy value | Adjuster |
|---|---|
| `'-10%'` | `new PercentageDiscount($name, 10)` |
| `'10%'`, `'+10%'` | `new PercentageFee($name, 10)` |
| `'-125'` | `new FixedDiscount($name, Price::fromDecimal('125'))` |
| `'125'`, `'+125'` | `new Shipping(Price::fromDecimal('125'), $name)` |

Item-level conditions have no direct counterpart: apply variant pricing to the line price before adding it.

### Data migration

The package ships no importer. Convert stored darryldecode carts with a one-off script on the application side:

1. Read the legacy rows (session dump, database table or cache entries).
2. Map every item to a line array: float prices become integer minor units (`Price::fromDecimal(...)`), `attributes` become `options`, `associatedModel` becomes the purchasable type. `Line::of(...)->toArray()` produces the stored shape.
3. Convert conditions using the table above; each adjuster is stored as `['class' => ..., 'data' => $adjuster->toArray()]`.
4. Write the payload for the original owner id so live cookies keep finding their carts:

```php
use TimurTurdyev\SimpleCart\Storage\StorageManager;

app(StorageManager::class)->driver('database')
    ->forOwner($ownerId)
    ->write('cart', ['lines' => $lines, 'adjusters' => $adjusters]);
```
