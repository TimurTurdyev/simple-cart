# Upgrading from darryldecode/laravelshoppingcart

This package is a clean rewrite, not a drop-in replacement. The ideas differ in three places:

- **Line identity.** The row id is a hash of the purchasable id, its class and the options. You no longer invent composite ids like `11-color-2` - pass the variant set as `options` and the package keeps lines apart.
- **Money.** Amounts are integer minor units inside the `Price` value object; floats never leak into the math.
- **Adjusters instead of conditions.** String values like `'-10%'` are gone. Discounts, fees and shipping are typed classes applied as a pipeline.

## API mapping

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

## Condition value mapping

| Legacy value | Adjuster |
|---|---|
| `'-10%'` | `new PercentageDiscount($name, 10)` |
| `'10%'`, `'+10%'` | `new PercentageFee($name, 10)` |
| `'-125'` | `new FixedDiscount($name, Price::fromDecimal('125'))` |
| `'125'`, `'+125'` | `new Shipping(Price::fromDecimal('125'), $name)` |

Item-level conditions have no direct counterpart: apply variant pricing to the line price before adding it.

## Data migration

Convert stored darryldecode carts into the `cart_lists` format:

```bash
php artisan cart:import-legacy legacy_carts \
    --owner-column=identifier \
    --data-column=cart_data \
    --dry-run
```

Drop `--dry-run` once the report looks right. The command accepts json payloads (and safely falls back to class-free unserialize), maps float prices to minor units and converts conditions using the table above. For custom storages, `TimurTurdyev\Cart\Legacy\LegacyImporter` is a public class - feed it the legacy arrays and write the resulting payload wherever you need.
