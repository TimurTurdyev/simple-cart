<p align="center">
  <img src="art/banner.svg" alt="Simple Cart" width="900">
</p>

# Simple Cart

Simple by design, not by capability. Корзина, закладки и сравнение товаров для Laravel 12+ (PHP 8.3+).

Каждый список (корзина, закладки, сравнение, свои списки) - один и тот же примитив с политикой из конфига. Деньги считает только корзина, и делает это через конвейер классов-корректировок. Без магических строк, без float, без обязательных миграций.

## Установка

```bash
composer require timurturdyev/simple-cart
php artisan vendor:publish --tag=simple-cart-config   # по желанию
```

Провайдер подхватывается автоматически через package discovery.

## Быстрый старт

```php
use TimurTurdyev\SimpleCart\Facades\Cart;
use TimurTurdyev\SimpleCart\Facades\Compare;
use TimurTurdyev\SimpleCart\Facades\Wishlist;

$line = Cart::add($item, quantity: 2, options: ['size' => 'm']);

Cart::setQuantity($line->id, 5);
Cart::total();                // Price: ->minor(), ->decimal(), ->format()

Wishlist::toggle($item);      // первый вызов добавляет, второй убирает
Wishlist::has($item);         // для кнопки-сердечка
Wishlist::moveToCart($item);

Compare::add($item);          // лимит берется из конфига
```

`$item` - любая модель, реализующая `Purchasable`:

```php
use TimurTurdyev\SimpleCart\Contracts\Purchasable;
use TimurTurdyev\SimpleCart\Support\Price;

class Chair extends Model implements Purchasable
{
    public function cartId(): string|int
    {
        return $this->id;
    }

    public function cartName(): string
    {
        return $this->name;
    }

    public function cartPrice(): Price
    {
        return Price::fromMinor($this->price);
    }
}
```

Без модели строка собирается вручную:

```php
use TimurTurdyev\SimpleCart\Line;

Cart::add(Line::of(11, 'Chair', Price::fromDecimal('19.99'), quantity: 2));
```

## Идентичность строки

Id строки = hash(id товара + нормализованные опции). Один товар с разными опциями сам раскладывается по разным строкам, составные id вручную склеивать не нужно:

```php
Cart::add($item, options: ['size' => 'm']);
Cart::add($item, options: ['size' => 'l']);    // вторая строка

Cart::has($item, options: ['size' => 'm']);    // true
```

Данные вне идентичности (зафиксированная картинка, метка времени) живут в `meta`:

```php
$line = Cart::add($item, meta: ['image' => $url]);

$line->meta('image');
$line->model();     // ленивый поиск модели по классу товара
Cart::models();     // все модели списка одним запросом на тип
```

Результат `model()` запоминается на время жизни строки, а `models()` греет этот кэш сразу для всего списка.

## Деньги

Суммы хранятся в целых минорных единицах внутри value-объекта `Price`. Конверсия в одной точке, округление half-up:

```php
Price::fromMinor(1999);        // 19.99
Price::fromDecimal('19.99');   // строка парсится точно

Cart::total()->minor();        // 1999
Cart::total()->format();       // "19.99"
```

## Скидки, сборы, доставка

```php
use TimurTurdyev\SimpleCart\Adjusters\PercentageDiscount;
use TimurTurdyev\SimpleCart\Adjusters\Shipping;

Cart::adjust(
    new PercentageDiscount('summer', percent: 10),
    new Shipping(Price::fromMinor(1500)),
);

Cart::removeAdjuster('summer');
Cart::totals()->breakdown();    // subtotal, каждая корректировка, total
```

В комплекте: `PercentageDiscount`, `FixedDiscount`, `PercentageFee`, `Shipping`. Корректировки применяются конвейером: каждая видит текущий total, поэтому последовательные скидки компаундятся и порядок важен. Своя корректировка - один класс:

```php
use TimurTurdyev\SimpleCart\Contracts\Adjuster;
use TimurTurdyev\SimpleCart\Support\Totals;

final readonly class GiftWrap implements Adjuster
{
    public function __construct(private Price $amount)
    {
    }

    public function name(): string
    {
        return 'gift-wrap';
    }

    public function adjust(Totals $totals): Totals
    {
        return $totals->addFee($this->name(), $this->amount);
    }

    public function toArray(): array
    {
        return ['amount' => $this->amount->minor()];
    }

    public static function fromArray(array $data): static
    {
        return new self(Price::fromMinor($data['amount']));
    }
}
```

Между запросами корректировки хранятся парами `{class, data}` и восстанавливаются с проверкой контракта. `unserialize` не используется.

## Списки

```php
'lists' => [
    'cart' => ['policy' => 'append'],
    'wishlist' => ['policy' => 'toggle'],
    'compare' => ['policy' => 'toggle', 'limit' => 4],
    'viewed' => ['policy' => 'toggle', 'limit' => 20],
],
```

| политика | поведение |
|---|---|
| `append` | повторное добавление суммирует количество |
| `toggle` | повторный `add` ничего не меняет, `toggle()` убирает |
| `limit` | новая строка сверх лимита кидает `ListLimitException` |

Новый тип списка - строка в конфиге, а не новый класс:

```php
use TimurTurdyev\SimpleCart\CartManager;

app(CartManager::class)->list('viewed')->toggle($item);

Cart::moveTo('wishlist', $line->id);   // отложить на потом
Wishlist::moveToCart($item);
```

## Хранилище

По умолчанию session: работает сразу после установки, пустые списки не оставляют записей. Переключение на базу - одна строка конфига:

```php
'storage' => 'database',
```

```bash
php artisan vendor:publish --tag=simple-cart-migrations
php artisan migrate
```

При логине гостевая корзина сливается с корзиной пользователя. Стратегии: `sum` (количества складываются), `keep` (строки пользователя важнее), `replace` (гостевая заменяет). Сливать умеют драйверы с `SupportsOwnerMerge` (database умеет из коробки), с остальными листенер просто пропускает логин.

Свой драйвер подключается снаружи, без правки пакета:

```php
use TimurTurdyev\SimpleCart\Storage\StorageManager;

app(StorageManager::class)->extend('redis', fn () => new RedisCartStorage());
```

Под нагрузкой добавьте сквозной кэш (секция `cache` конфига): ключ на корзину, чтение с прогретым ключом до базы не доходит. Работает с любым драйвером, кастомным тоже.

## Идентичность корзины

За владельца корзины отвечает слой `CartIdentity`. По умолчанию сессия. Нужна гостевая корзина дольше сессии - ставьте cookie-драйвер:

```php
'identity' => [
    'driver' => 'cookie',
    'cookie' => [
        'name' => env('SIMPLE_CART_COOKIE', 'simple_cart_id'),
        'ttl_minutes' => 60 * 24 * 30,
    ],
],
```

Cookie ставится лениво, при первой реальной записи: гость с пустой корзиной cookie не получит. Залогиненного пользователя определяет auth id. Шифрование - штатный `EncryptCookies` приложения.

Свой вариант подключается через `IdentityManager::extend()`, как у хранилища.

## События

`LineAdded`, `LineUpdated`, `LineRemoved`, `ListCleared`. В каждом - имя списка и строка. Отключаются через `'events' => false`.

## Аналитика и брошенные корзины

Таблица `simple_cart_lists` открыта для чтения: колонки `owner`, `list`, `payload` и таймстампы, формат меняет разве что мажорная версия.

Брошенные корзины админка находит SQL-запросом по `updated_at`. Внутрь payload пускают json-функции БД и модель `TimurTurdyev\SimpleCart\Storage\CartRecord`. Цены в минорных единицах, курсов валют тут нет.

## Переход с darryldecode/laravelshoppingcart

Таблица соответствия API и рецепт переноса данных - в [UPGRADE.md](UPGRADE.md). Встроенного импорта нет: старые корзины переносит одноразовый скрипт приложения через `forOwner($ownerId)->write(...)`, id владельцев не меняются, живые cookie продолжают находить свои корзины.

## Тесты

```bash
make check
```

## Лицензия

MIT. См. [LICENSE.md](LICENSE.md).
