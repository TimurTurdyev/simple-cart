# Upgrade Guide

## From 1.0 to 1.1

- `Storage\DatabaseStorage` is now constructed with a `Contracts\CartIdentity` instead of an owner `Closure`. The driver is wired by `StorageManager`, so this only affects code instantiating `DatabaseStorage` directly - wrap your owner resolution into a `CartIdentity` (see `Identity\FixedIdentity`).
- The `cart:import-legacy` command and the public `Legacy\LegacyImporter` class are removed. Data migration is an application-side one-off script now: build the new payload and write it for an explicit owner via `forOwner()` (see [Data migration](#data-migration) below).
- New config sections `identity` and `cache` ship with backwards-compatible defaults (`identity.driver=session`, `cache.enabled=false`); published configs keep working without changes.

## From darryldecode/laravelshoppingcart

This package is a clean rewrite, not a drop-in replacement. The ideas differ in three places:

- **Line identity.** The row id is a hash of the purchasable id, its class and the options. You no longer invent composite ids like `11-color-2` - pass the variant set as `options` and the package keeps lines apart.
- **Money.** Amounts are integer minor units inside the `Price` value object; floats never leak into the math.
- **Adjusters instead of conditions.** String values like `'-10%'` are gone. Discounts, fees and shipping are typed classes applied as a pipeline.

### API mapping

| darryldecode | laravel-cart |
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
| `Cart::removeCartCondition($name)` | `Cart::withoutAdjuster($name)` |
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
use TimurTurdyev\Cart\Storage\StorageManager;

app(StorageManager::class)->driver('database')
    ->forOwner($ownerId)
    ->write('cart', ['lines' => $lines, 'adjusters' => $adjusters]);
```
