# PHPArkitect reference

`phparkitect/phparkitect` — architectural rules written as plain PHP and verified in CI.
Supports PHP 8.0–8.5. Repo/docs: https://github.com/phparkitect/arkitect

## Install

```bash
composer require --dev phparkitect/phparkitect
vendor/bin/phparkitect init   # scaffolds phparkitect.php (leaves an existing one untouched)
```

If it conflicts with project dependencies, use the PHAR and pass
`--autoload=vendor/autoload.php`.

## The rule shape

Every rule is a selector + a constraint + a reason:

```php
Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\Domain'))   // selector: which classes
    ->should(new DependsOnlyOnTheseNamespaces('App\Domain', 'App\Shared'))  // constraint
    ->because('the domain must stay free of infrastructure and framework code'); // reason
```

`->because()` is not decoration — it's the message shown when the rule fails, so make it
explain the intent to whoever hit it.

## Config file (`phparkitect.php`)

```php
<?php
declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Rules\Rule;
// ... expression imports ...

return static function (Config $config): void {
    $src = ClassSet::fromDir(__DIR__ . '/src');
    $rules = [];
    // $rules[] = Rule::allClasses()->that(...)->should(...)->because(...);
    $config->add($src, ...$rules);
};
```

`ClassSet::fromDir()` accepts several directories; use `->excludePath('**/Tests/')` to skip
paths (`**` matches across directory levels).

## Useful expressions (`Arkitect\Expression\ForClasses\...`)

Dependency direction:
- `DependsOnlyOnTheseNamespaces(...$namespaces)` — the class may depend on the listed
  namespaces only. PHP core classes are auto-allowed, so you don't list them.
- `NotDependsOnTheseNamespaces(...$namespaces)` — the class must not depend on any listed
  namespace. Good for "Application must not touch Infrastructure/Presentation".
- `NotHaveDependencyOutsideNamespace('App\Domain')` — nothing outside the given namespace.

Placement & naming:
- `ResideInOneOfTheseNamespaces('App\Controller')`
- `HaveNameMatching('*Controller')` / `NotHaveNameMatching(...)`
- `Implement('*RepositoryInterface')`, `Extend(...)`, `HaveMethod('__invoke')`

Shape:
- `IsFinal()`, `IsAbstract()`, `IsReadonly()`, `IsInterface()`, `IsEnum()`

Combine constraints with `->andShould(...)` and narrow selectors with `->except('App\...')`.

## Example rules for the five-layer model

```php
// Domain depends only on itself and Shared.
$rules[] = Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\Domain'))
    ->should(new DependsOnlyOnTheseNamespaces('App\Domain', 'App\Shared'))
    ->because('dependencies point inward; the domain stays framework-free');

// Application must not reach out to adapters or delivery.
$rules[] = Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\Application'))
    ->should(new NotDependsOnTheseNamespaces('App\Infrastructure', 'App\Presentation'))
    ->because('use cases must not depend on infrastructure or presentation');

// Naming convention keeps the structure self-documenting.
$rules[] = Rule::allClasses()
    ->that(new ResideInOneOfTheseNamespaces('App\Presentation\Http\Controller'))
    ->should(new HaveNameMatching('*Controller'))
    ->because('controllers are discoverable by their suffix');

// DTOs are immutable data carriers.
$rules[] = Rule::allClasses()
    ->that(new HaveNameMatching('*Dto'))
    ->should(new IsFinal())
    ->andShould(new IsReadonly())
    ->because('DTOs should not be extended or mutated');
```

## Commands & baseline

```bash
vendor/bin/phparkitect check                       # run all rules
vendor/bin/phparkitect check --config=phparkitect.php
vendor/bin/phparkitect check --stop-on-failure     # halt at first violation

# Adopt on a dirty codebase: record current violations, fail only on new ones
vendor/bin/phparkitect check --generate-baseline
vendor/bin/phparkitect check --use-baseline=phparkitect-baseline.json
```

`--format=json` or `--format=gitlab` for CI integrations. Use
`phparkitect debug:expression <Expression> <args>` to preview which classes a selector
matches before committing a rule.
