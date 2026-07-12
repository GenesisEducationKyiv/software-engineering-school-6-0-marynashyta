<?php

/**
 * PHPArkitect configuration — fine-grained architectural rules for the API monolith (see
 * docs/architecture/domain-map.md section 4 and deptrac.yaml).
 *
 * Deptrac's namespace collectors (deptrac.yaml) draw the layer boundaries; PHPArkitect
 * complements it with what a namespace selector can express cleanly: the naming convention
 * the collectors rely on (Domain has no dedicated namespace segment in every module — GitHub
 * and Observability keep their ports directly under Domain\, so *Interface naming is what
 * actually marks a port there), adapter/value-object shape, and the one edge Deptrac's
 * namespace-based Domain collector can't see: a Domain class quietly reaching into
 * Infrastructure or Presentation via a fully-qualified reference.
 *
 * Run: vendor/bin/phparkitect check
 */

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\IsFinal;
use Arkitect\Expression\ForClasses\IsInterface;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $src = ClassSet::fromDir(__DIR__ . '/src')
        ->excludePath('*/Modules/Scanner/*');

    $rules = [];

    // ---------------------------------------------------------------------
    // Dependency direction — the one edge a namespace selector can see cleanly:
    // Domain must stay innermost regardless of which module it belongs to.
    // ---------------------------------------------------------------------

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(
            'App\Modules\GitHub\Domain',
            'App\Modules\Observability\Domain',
            'App\Modules\Subscription\Domain',
        ))
        ->should(new NotDependsOnTheseNamespaces([
            'App\Modules\GitHub\Infrastructure',
            'App\Modules\Notification\Infrastructure',
            'App\Modules\Observability\Infrastructure',
            'App\Modules\Subscription\Infrastructure',
            'App\Bootstrap',
        ]))
        ->because('business rules must not depend on adapters or delivery — deptrac.yaml enforces this per layer, this rule catches it even if a class is misclassified by namespace');

    // ---------------------------------------------------------------------
    // Naming & shape conventions — the layers in deptrac.yaml are keyed off
    // these, so breaking them would silently misclassify a class.
    // ---------------------------------------------------------------------

    $rules[] = Rule::allClasses()
        ->that(new HaveNameMatching('*Interface'))
        ->should(new IsInterface())
        ->because('the *Interface suffix is the port-naming convention deptrac.yaml relies on to find a port that shares a Domain/Application namespace with its value objects');

    $rules[] = Rule::allClasses()
        ->that(new Implement('*Interface'))
        ->should(new IsFinal())
        ->because('adapters are swapped via DI (config/container.php), never extended');

    $config->add($src, ...$rules);
};
