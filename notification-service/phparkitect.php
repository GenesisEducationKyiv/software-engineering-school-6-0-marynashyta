<?php

/**
 * PHPArkitect configuration — fine-grained architectural rules (see
 * docs/architecture/ARCHITECTURE.md section 5 and ADR-0001).
 *
 * This service shares one port (MailerInterface, root namespace) between two delivery
 * mechanisms (Handler/ for HTTP, Consumer/ for AMQP). Deptrac's per-FQCN collectors
 * (deptrac.yaml) are what precisely separate the port from its adapter when they'd otherwise
 * share a namespace; PHPArkitect complements it here with what a namespace selector *can*
 * express cleanly: naming conventions the layering relies on, adapter/value-object shape,
 * and the dependency edges that don't cross a shared namespace (Presentation must not reach
 * into the AMQP consumer package or settings directly; the transport-agnostic use case must
 * not import a transport library).
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
    // Dependency direction — the edges a namespace selector can see cleanly.
    // ---------------------------------------------------------------------

    // HTTP delivery must not reach into the AMQP consumer package or settings directly;
    // it only ever needs the MailerInterface port (which shares a namespace with Mailer,
    // so Deptrac's per-class collectors are what actually police that edge).
    $rules[] = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces(
            'NotificationService\Handler',
            'NotificationService\Health',
            'NotificationService\Middleware',
        ))
        ->should(new NotDependsOnTheseNamespaces(['NotificationService\Consumer', 'NotificationService\Config']))
        ->because('HTTP delivery is a separate concern from the AMQP consumer pipeline and its settings');

    // The one transport-agnostic use case must stay transport-agnostic: no AMQP library,
    // no concrete mailer client, only the port.
    $rules[] = Rule::allClasses()
        ->that(new HaveNameMatching('MessageHandler'))
        ->should(new NotDependsOnTheseNamespaces(['PhpAmqpLib', 'PHPMailer\PHPMailer']))
        ->because('MessageHandler must stay reusable regardless of which queue library delivers the message');

    // ---------------------------------------------------------------------
    // Naming & shape conventions — the layers in deptrac.yaml are keyed off
    // these, so breaking them would silently misclassify a class.
    // ---------------------------------------------------------------------

    $rules[] = Rule::allClasses()
        ->that(new HaveNameMatching('*Interface'))
        ->should(new IsInterface())
        ->because('the *Interface suffix is the port-naming convention the Domain layer collector in deptrac.yaml relies on');

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
