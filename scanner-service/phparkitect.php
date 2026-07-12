<?php

/**
 * PHPArkitect configuration — fine-grained architectural rules (see
 * docs/architecture/ARCHITECTURE.md section 5 and ADR-0001).
 *
 * This service is feature-first (GitHub/, Notification/, Subscription/), each with a port
 * interface and one adapter sharing the same namespace. That means Deptrac's per-FQCN
 * collectors (deptrac.yaml) — not namespace-prefix selectors — are what precisely separates
 * a port from its adapter; PHPArkitect complements it here with what a namespace selector
 * *can* express cleanly: naming conventions the layering relies on, adapter/value-object
 * shape, and the one dependency direction that doesn't cross a shared namespace
 * (`Scanner\ReleaseScanner` must not reach into `Infrastructure`/`Config` directly).
 *
 * Run: vendor/bin/phparkitect check
 */

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\IsFinal;
use Arkitect\Expression\ForClasses\IsInterface;
use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $src = ClassSet::fromDir(__DIR__ . '/src');

    $rules = [];

    // ---------------------------------------------------------------------
    // Dependency direction — the one edge a namespace selector can see cleanly:
    // the use case must reach Infrastructure/Config only through the ports it
    // is constructed with, never by importing a concrete helper or setting.
    // ---------------------------------------------------------------------

    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('ScannerService\Scanner'))
        ->should(new NotDependsOnTheseNamespaces(['ScannerService\Infrastructure', 'ScannerService\Config']))
        ->because('the use case is wired through ports only; resilience and settings are supplied by the composition root (config/container.php)');

    // ---------------------------------------------------------------------
    // Naming & shape conventions — the layers in deptrac.yaml are keyed off
    // these, so breaking them would silently misclassify a class.
    // ---------------------------------------------------------------------

    $rules[] = Rule::allClasses()
        ->that(new HaveNameMatching('*Interface'))
        ->should(new IsInterface())
        ->because('the *Interface suffix is the port-naming convention the Application layer collector in deptrac.yaml relies on');

    $rules[] = Rule::allClasses()
        ->that(new Implement('*Interface'))
        ->should(new IsFinal())
        ->because('adapters are swapped via DI (config/container.php), never extended');

    $rules[] = Rule::allClasses()
        ->that(new HaveNameMatching('*Config'))
        ->should(new IsFinal())
        ->andShould(new IsReadonly())
        ->because('typed settings are immutable value objects built once at the composition root');

    $config->add($src, ...$rules);
};
