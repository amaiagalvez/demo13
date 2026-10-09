# Plan: Create `customers13` Package and Extract Customer Functionality

## Overview
Extract all Customer-related code from `demo13` into a new Laravel package `customers13` at `/home/amaia/Mahaigaina/l13/packages/customers13`. The package will depend on `basics13` (dev dependency for tests). The customers table will be renamed to `CUM_customers`.

---

## Phase 1: Create Package Structure

### 1.1 Package Skeleton
```
packages/customers13/
├── composer.json
├── phpunit.xml.dist
├── src/
│   ├── ServiceProvider.php
│   ├── Models/
│   │   └── Customer.php
│   ├── Policies/
│   │   └── CustomerPolicy.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── CustomerController.php
│   │   │   ├── CustomerTrashController.php
│   │   │   └── CustomerArchivedController.php
│   │   └── Requests/
│   │       ├── CustomerRequest.php
│   │       ├── CustomerListRequest.php
│   │       ├── CustomerRestoreRequest.php
│   │       ├── CustomerDestroyRequest.php
│   │       └── CustomerSelectOptionsRequest.php
│   ├── Queries/
│   │   └── Customers/
│   │       ├── CustomerListQuery.php
│   │       └── CustomerSelectOptionsQuery.php
│   ├── Transformers/
│   │   └── CustomerListTransformer.php
│   └── Database/
│       ├── Migrations/
│       │   └── 2026_09_25_000000_create_cum_customers_table.php
│       └── Factories/
│           └── CustomerFactory.php
├── resources/
│   ├── views/
│   │   ├── customers/
│   │   │   ├── list.blade.php
│   │   │   └── form.blade.php
│   │   └── components/
│   │       └── planning/
│   │           └── customer-chips.blade.php
│   └── lang/
│       ├── en/
│       │   ├── validation.php
│       │   ├── actions.php
│       │   └── messages.php
│       ├── es/
│       │   ├── validation.php
│       │   ├── actions.php
│       │   └── messages.php
│       ├── fr/
│       │   ├── validation.php
│       │   ├── actions.php
│       │   └── messages.php
│       └── eu/
│           ├── validation.php
│           ├── actions.php
│           └── messages.php
├── config/
│   └── customers13.php
└── tests/
    ├── Unit/
    │   ├── Customers/
    │   │   ├── CustomerTest.php
    │   │   └── CustomerTranslationTest.php
    │   ├── Policies/
    │   │   └── CustomerPolicyTest.php
    │   ├── Queries/
    │   │   └── CustomerListQueryTest.php
    │   ├── Validation/
    │   │   ├── CustomerRequestTest.php
    │   │   └── CustomerRestoreRequestTest.php
    │   └── Support/
    │       └── Database/
    │           └── UniqueConstraintViolationTest.php (if needed)
    ├── Feature/
    │   └── Customers/
    │       ├── CustomerCrudTest.php
    │       ├── CustomerTrashTest.php
    │       └── CustomerListQueryTest.php
    └── Browser/
        └── Customers/
            └── CustomerCrudTest.php
```

### 1.2 Package composer.json
```json
{
    "name": "amaia/customers13",
    "description": "Customer management for Laravel 13: CRUD, soft deletes, archive, trash, select options, planning chips.",
    "type": "library",
    "license": "MIT",
    "version": "1.0.0",
    "authors": [{"name": "Amaia", "email": "amaia@example.com"}],
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.0",
        "livewire/flux": "^2.0",
        "amaia/basics13": "*"
    },
    "require-dev": {
        "laravel/pint": "^1.27",
        "phpunit/phpunit": "^12.0",
        "mockery/mockery": "^1.6",
        "larastan/larastan": "^3.9",
        "laravel/dusk": "^8.7"
    },
    "autoload": {
        "psr-4": {
            "Customers13\\": "src/"
        },
        "files": [
            "src/Database/helpers_global.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Customers13\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": ["Customers13\\ServiceProvider"]
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true,
    "config": {"sort-packages": true, "allow-plugins": {"pestphp/pest-plugin": true}}
}
```

### 1.3 ServiceProvider
- Load views from `resources/views` as `customers13`
- Load translations from `resources/lang` as `customers13`
- Publish config, views, lang, migrations
- Register the migration path

---

## Phase 2: Migrate Code to Package

### 2.1 Model: `src/Models/Customer.php`
- Namespace: `Customers13\Models`
- Table: `CUM_customers` (protected `$table = 'CUM_customers';`)
- Policy: `Customers13\Policies\CustomerPolicy`
- Factory: `Customers13\Database\Factories\CustomerFactory`
- Traits: `HasFactory`, `SoftDeletes`, `Basics13\Concerns\TracksAuditColumns`
- Relationships: `projects()`, `epics()` (return types updated to use `Customers13\Models\Project` when that exists, or keep as string for now)
- Casts: `active` boolean

### 2.2 Policy: `src/Policies/CustomerPolicy.php`
- Namespace: `Customers13\Policies`
- Same logic as current

### 2.3 Controllers
- `src/Http/Controllers/CustomerController.php` → `Customers13\Http\Controllers`
- `src/Http/Controllers/CustomerTrashController.php` → `Customers13\Http\Controllers`
- `src/Http/Controllers/CustomerArchivedController.php` → `Customers13\Http\Controllers`
- All imports updated to `Customers13\` namespaces
- Extend from `Basics13\Http\Controllers\Controller`, `ArchivedController`, `TrashController`

### 2.4 Requests
- `src/Http/Requests/CustomerRequest.php` → `Customers13\Http\Requests`
- `src/Http/Requests/CustomerListRequest.php` → `Customers13\Http\Requests`
- `src/Http/Requests/CustomerRestoreRequest.php` → `Customers13\Http\Requests`
- `src/Http/Requests/CustomerDestroyRequest.php` → `Customers13\Http\Requests`
- `src/Http/Requests/CustomerSelectOptionsRequest.php` → `Customers13\Http\Requests`
- Use `Customers13\Models\Customer` and `Basics13\Http\Requests\*` base classes

### 2.5 Queries
- `src/Queries/Customers/CustomerListQuery.php` → `Customers13\Queries\Customers`
- `src/Queries/Customers/CustomerSelectOptionsQuery.php` → `Customers13\Queries\Customers`
- Extend `Basics13\Queries\ListQueryBase`
- Use `Customers13\Models\Customer` and `Customers13\Models\EpicComment` (or string if not extracted)

### 2.6 Transformer
- `src/Transformers/CustomerListTransformer.php` → `Customers13\Transformers`
- Extend `Basics13\Transformers\ListTransformer`
- Use `Customers13\Models\Customer`
- Routes use `customers13.` prefix (e.g., `customers13.customers.index`)

### 2.7 Migration
- `src/Database/Migrations/2026_09_25_000000_create_cum_customers_table.php`
- Table name: `CUM_customers`
- Use `Basics13\Support\Database\Helpers::addCommonColumns()` and `addUniqueActiveNameIndex('CUM_customers')`
- Add audit columns

### 2.8 Factory
- `src/Database/Factories/CustomerFactory.php` → `Customers13\Database\Factories`
- Uses `Customers13\Models\Customer`

### 2.9 Views
- `resources/views/customers/list.blade.php` → update all route references to `customers13.` prefix
- `resources/views/customers/form.blade.php` → update request class reference
- `resources/views/components/planning/customer-chips.blade.php` → move to package

### 2.10 Translations
- Extract Customer-related keys from demo13 `lang/*.json` into package `resources/lang/{en,es,fr,eu}/{validation,actions,messages}.php`
- Keys: "Customer", "Customers", "Create customer", "Edit customer", "New customer", "Save customer", "Update customer", "Active customers", "No customers found", "No customers match your search", "No customers yet", "Record Name already in trash", "A deleted record already uses the name :name", "Create a new customer", "Restore the deleted record instead", "Unable to load customers", etc.

### 2.11 Config
- `config/customers13.php` - optional, for any package-specific config

---

## Phase 3: Update demo13 to Use Package

### 3.1 Update demo13 composer.json
- Add local path repository for `customers13` (like basics13)
- Require `amaia/customers13:*` 
- Remove Customer-related autoload paths (they'll be in the package)

### 3.2 Remove from demo13 (DELETE these files)
```
app/Models/Customer.php
app/Policies/CustomerPolicy.php
app/Http/Controllers/CustomerController.php
app/Http/Controllers/CustomerTrashController.php
app/Http/Controllers/CustomerArchivedController.php
app/Http/Requests/CustomerRequest.php
app/Http/Requests/CustomerListRequest.php
app/Http/Requests/CustomerRestoreRequest.php
app/Http/Requests/CustomerDestroyRequest.php
app/Http/Requests/CustomerSelectOptionsRequest.php
app/Queries/Customers/CustomerListQuery.php
app/Queries/Customers/CustomerSelectOptionsQuery.php
app/Transformers/CustomerListTransformer.php
database/migrations/2026_09_25_000000_create_customers_table.php
database/factories/CustomerFactory.php
resources/views/customers/list.blade.php
resources/views/customers/form.blade.php
resources/views/components/planning/customer-chips.blade.php
tests/Unit/Customers/CustomerTest.php
tests/Unit/Customers/CustomerTranslationTest.php
tests/Unit/Policies/CustomerPolicyTest.php
tests/Unit/Queries/CustomerListQueryTest.php
tests/Unit/Validation/CustomerRequestTest.php
tests/Unit/Validation/CustomerRestoreRequestTest.php
tests/Feature/Customers/CustomerCrudTest.php
tests/Feature/Customers/CustomerTrashTest.php
tests/Feature/Customers/CustomerListQueryTest.php
tests/Browser/Customers/CustomerCrudTest.php
```

### 3.3 Update demo13 Files That Reference Customer

**routes/web.php**
- Remove Customer controller imports
- Remove Customer routes (they'll be registered by the package's ServiceProvider)

**app/Models/Project.php**
- Update `customer()` relationship to use `Customers13\Models\Customer`
- Update `fullName()` method

**app/Providers/AppServiceProvider.php**
- Remove `Customer` import and `blockDeletionWithChildren()` hook for Customer (move to package's ServiceProvider or Model boot)

**app/Queries/Projects/ProjectListQuery.php** - uses Customer
**app/Queries/Epics/EpicListQuery.php** - uses Customer
**app/Queries/Planning/PlanningQuery.php** - uses Customer
**app/Queries/Timeline/TimelineQuery.php** - uses Customer

**database/factories/ProjectFactory.php** - uses Customer
**database/seeders/DevelopTableSeeder.php** - uses Customer
**database/seeders/EdgeCaseSeeder.php** - uses Customer

**storage/app/perf/customer-list-bench.php** - uses Customer
**storage/app/perf/index-probe.php** - uses Customer

**tests/Browser/Projects/ProjectCustomerSelectTest.php** - uses Customer
**tests/Feature/Projects/CustomerSelectOptionsTest.php** - uses Customer

### 3.4 Update Remaining demo13 Tests
- Update imports to use `Customers13\Models\Customer`
- Update route names to use `customers13.` prefix

---

## Phase 4: Package ServiceProvider Registration

### 4.1 Package ServiceProvider boot()
```php
public function boot(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'customers13');
    $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'customers13');
    
    $this->publishes([
        __DIR__.'/../config/customers13.php' => config_path('customers13.php'),
    ], 'customers13-config');
    
    // Register routes
    $this->registerRoutes();
    
    // Register model observer for deletion blocking
    Customer::deleting(function (Customer $customer): bool {
        $locked = DB::transaction(static fn (): Customer => Customer::withTrashed()
            ->whereKey($customer->getKey())
            ->lockForUpdate()
            ->firstOrFail());
        return ! $locked->projects()->withTrashed()->exists();
    });
}

protected function registerRoutes(): void
{
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('customers/archived', [CustomerArchivedController::class, 'index'])->name('customers.archived.index');
        Route::patch('customers/archived/{customer}', [CustomerArchivedController::class, 'activate'])->whereNumber('customer')->name('customers.archived.activate');
        Route::patch('customers/{customer}/archive', [CustomerArchivedController::class, 'archive'])->whereNumber('customer')->name('customers.archive');
        Route::get('customers/trash', [CustomerTrashController::class, 'index'])->name('customers.trash.index');
        Route::patch('customers/trash/{customer}', [CustomerTrashController::class, 'restore'])->whereNumber('customer')->name('customers.trash.restore');
        Route::delete('customers/trash/{customer}', [CustomerTrashController::class, 'destroy'])->whereNumber('customer')->name('customers.trash.destroy');
        Route::get('customers/options', [CustomerController::class, 'selectOptions'])->name('customers.options');
        Route::resource('customers', CustomerController::class)->only(['index', 'store', 'update', 'destroy']);
    });
}
```

---

## Phase 5: Handle Cross-Package Dependencies

### 5.1 Project Model (in demo13) → Customer (in customers13)
- `Project::customer()` returns `BelongsTo<Customers13\Models\Customer>`
- This works because the package is installed

### 5.2 Queries in demo13 that use Customer
- `ProjectListQuery`, `EpicListQuery`, `PlanningQuery`, `TimelineQuery`
- Update imports to `Customers13\Models\Customer`
- Update relationship calls

### 5.3 Seeders/Factories in demo13
- Update to use `Customers13\Models\Customer` and `Customers13\Database\Factories\CustomerFactory`

### 5.4 Performance scripts
- Update to use package classes

### 5.5 Tests in demo13
- Update imports and route names

---

## Phase 6: Language Files

### 6.1 Extract Customer translations from demo13 lang/*.json
Move to package `resources/lang/{locale}/{validation,actions,messages}.php`

### 6.2 Keep in demo13 lang/*.json
- All non-Customer translations (auth, dashboard, projects, epics, planning, timeline, settings, etc.)

---

## Phase 7: Testing

### 7.1 Package Tests
- Copy all Customer tests to package `tests/` directory
- Update namespaces to `Customers13\Tests\...`
- Use `Customers13\Models\Customer`, etc.
- Use `RefreshDatabase` / `LazilyRefreshDatabase` traits
- Run package tests: `cd packages/customers13 && ../vendor/bin/phpunit`

### 7.2 Demo13 Tests
- Update demo13 tests to use package
- Run demo13 tests: `DX php artisan test`

---

## Phase 8: Development Dependency Setup

### 8.1 In demo13 composer.json
```json
"repositories": [
    {"type": "path", "url": "/packages/basics13", "options": {"symlink": true}},
    {"type": "path", "url": "/packages/customers13", "options": {"symlink": true}}
],
"require": {
    "amaia/basics13": "*",
    "amaia/customers13": "*",
    ...
}
```

### 8.2 In customers13 composer.json
```json
"require-dev": {
    "amaia/basics13": "*",
    ...
}
```

---

## Phase 9: Migration & Database

### 9.1 Run Migration
- `DX php artisan migrate` (will run package migration creating `CUM_customers`)
- Old `customers` table can be dropped (not in production)

### 9.2 Update Foreign Keys
- `projects` table has `customer_id` foreign key to `CUM_customers`
- This should work automatically since we're just renaming the table

---

## Phase 10: Verification

### 10.1 Run Architecture Tests
```bash
DX php artisan test --compact tests/Unit/ArchitectureTest tests/Unit/ValidationCoverageTest tests/Unit/ModelSchemaParityTest tests/Unit/ResourceUniformityTest
```

### 10.2 Run All Tests
```bash
DX php artisan test --parallel --compact
```

### 10.3 Static Analysis
```bash
DX ./vendor/bin/phpstan analyse
```

### 10.4 Code Style
```bash
DX ./vendor/bin/pint --dirty --format agent
```

---

## Order of Execution

1. **Create package structure** (composer.json, ServiceProvider, config)
2. **Move Model, Policy, Factory, Migration** to package
3. **Move Controllers, Requests, Queries, Transformer** to package
4. **Move Views, Translations, Components** to package
5. **Move Tests** to package
6. **Update package ServiceProvider** (routes, model observers)
7. **Update demo13 composer.json** to require package
8. **Remove Customer code from demo13**
9. **Update demo13 references** (Project, Queries, Seeders, Tests)
10. **Run migrations** (create CUM_customers)
11. **Run all tests** (package + demo13)
12. **Static analysis & lint**

---

## Key Considerations

| Item | Decision |
|------|----------|
| Table name | `CUM_customers` (changed in migration) |
| Route prefix | `customers13.` (e.g., `customers13.customers.index`) |
| View namespace | `customers13::` |
| Translation namespace | `customers13::` |
| Namespace root | `Customers13\` |
| basics13 dependency | Required in `require`, dev in `require-dev` |
| Demo13 dependency | Path repository + require `amaia/customers13:*` |

---

## Files That Need Cross-Reference Updates in demo13

After package extraction, these demo13 files need import/namespace updates:
- `app/Models/Project.php`
- `app/Providers/AppServiceProvider.php`
- `app/Queries/Projects/ProjectListQuery.php`
- `app/Queries/Epics/EpicListQuery.php`
- `app/Queries/Planning/PlanningQuery.php`
- `app/Queries/Timeline/TimelineQuery.php`
- `database/factories/ProjectFactory.php`
- `database/seeders/DevelopTableSeeder.php`
- `database/seeders/EdgeCaseSeeder.php`
- `storage/app/perf/customer-list-bench.php`
- `storage/app/perf/index-probe.php`
- `tests/Browser/Projects/ProjectCustomerSelectTest.php`
- `tests/Feature/Projects/CustomerSelectOptionsTest.php`
- Any other test files referencing Customer

---

## Notes

- The `UniqueConstraintViolation` class is in `basics13`, so package uses `Basics13\Support\Database\UniqueConstraintViolation`
- The `TracksAuditColumns` trait is in `basics13`
- Base controllers (`Controller`, `ArchivedController`, `TrashController`) are in `basics13`
- Base requests (`SearchableListRequest`, `RestoreRequest`, etc.) are in `basics13`
- `ListQueryBase` and `ListTransformer` are in `basics13`
- View components (`x-basics13::components.list.*`, `x-forms.tracked-resource`, `x-name-conflict-modal`) are from `basics13`
- Flux components (`flux:table`, `flux:modal`, etc.) are from `livewire/flux`