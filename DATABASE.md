# Model customization

Most of the time you will want to change the table name of the Model, maybe because it collides with another. Use the `customize()` method with the table name. Preferably, you would do this in your `bootstrap/app.php`.

```php
use Illuminate\Foundation\Application;
use Vendor\Package\Models\Car;

return Application::configure(basePath: dirname(__DIR__))
    ->booted(function () {
        // Customize the model table name.
        Car::customize('vehicles');
    })->create();
```

You can further customize the Models included in this package with a callback that receives a Model instance. This method is always executed when instancing a Model.

For example, you may hide some attributes or change the table and connection the Model by default.

```php
use Vendor\Package\Models\Car;

Car::customize(function (Car $model) {
    $model->setTable('vehicles');
    $model->setConnection('readonly-mysql');
            
    $model->setHidden('private_notes');
});
```

> [!TIP]
>
> There is no need to alter the Model migration if you change the table. The migration automatically picks up the table and connection you set in through the `customize()` method.
>

# Migration customization

The library you have installed comes with a very hands-off approach for migrations. If you check the new migrations published at `database/migrations`, you will find something very similar to this:

```php
// database/migrations/2027_01_01_193000_create_cars_table.php
use Vendor\Package\Models\Car;

return Car::migration();
```

Worry not, the migration will still work. It has been _simplified_ for easy customization.

## Adding columns

To add columns to the migration, add a callback to the `with()` method. The callback will receive the table blueprint, so you can modify the table before it is created. New columns will be appended to the table blueprint.

```php
use Illuminate\Database\Schema\Blueprint;
use Laragear\Package\Models\Car;

return Car::migration()->with(function (Blueprint $table) {
    $table->boolean('is_cool')->default(true);
    $table->string('color');
});
```

### Relationships

If the package supports it, you may add relationships through their proper migration columns. For example, if we want to add the `car` relationship to the package Model, we can use the native `resolveRelationUsing()` on your `bootstrap/app.php()`.

```php
use App\Models\Driver;
use Illuminate\Foundation\Application;
use Vendor\Package\Models\Car;

return Application::configure(basePath: dirname(__DIR__))
    ->booted(function () {
        // Add the relationship.
        Car::resolveRelationUsing('driver', function (Driver $driver) {
            return $driver->belongsTo(Driver::class, 'driver_id');
        })
    })->create();
```

In the published package migration, you should be able to add the required column to connect your model like usual. In this case, we can use the [`foreignIdFor()`](https://laravel.com/docs/migrations#column-method-foreignIdFor) method to safely set the proper column name and type.

```php
use App\Models\Driver;
use Illuminate\Database\Schema\Blueprint;
use Vendor\Package\Models\Car;

return Car::migration()->with(function (Blueprint $table) {
    // ...
    
    $table->foreignIdFor(Driver::class, 'driver_id');
});
```

## After Up & Before Down

If you need to execute logic _after_ creating the table, or _before_ dropping it, use the `afterUp()` and `beforeDown()` methods, respectively.

```php
use Illuminate\Database\Schema\Blueprint;
use Vendor\Package\Models\Car;

return Car::migration()
    ->afterUp(function (Blueprint $table) {
        $table->foreignId('sociable_id')->references('id')->on('users');
    })
    ->beforeDown(function (Blueprint $table) {
        $table->dropForeign('sociable_id');
    });
```

### Morphs

Some packages will create a morph relation automatically to easily handle the default relationship across multiple models. For example, a morph migration to support an `owner` being either one of your models `user` or `business`.

```php
use Vendor\Package\Models\Car;

$car = Car::find(1);

$owner = $driver->owner; // App/Models/User or App/Models/Business
```

You may find yourself with models that use UUID, ULID or other types of primary keys, but with a migration that creates morphs for integer primary keys.

You can change the morph type with the `morph...` property access preferably, or the `morph()` method with `numeric`, `uuid` or `ulid` if you need to also set an index name (in case your database engine doesn't play nice with large ones).

```php
use Illuminate\Database\Schema\Blueprint;
use Vendor\Package\Models\Car;

return Car::migration()->morphUuid;

return Car::migration()->morph('uuid', 'shorter_morph_index_name');
```
