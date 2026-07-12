<?php

declare(strict_types=1);

namespace Tests\E2E\Pages;

use Playwright\Page\PageInterface;

/**
 * Page Object for public/subscribe.html.
 *
 * Locators are centralised here so test methods stay free of HTML details.
 */
final class SubscribePage
{
    public function __construct(private readonly PageInterface $page)
    {
    }

    public function fillEmail(string $email): void
    {
        $this->page->locator('input[name="email"]')->fill($email);
    }

    public function fillRepo(string $repo): void
    {
        $this->page->locator('input[name="repo"]')->fill($repo);
    }

    public function submit(): void
    {
        $this->page->locator('button[type="submit"]')->click();
    }

    /** Wait until the alert box becomes visible after a form submission. */
    public function waitForAlert(): void
    {
        $this->page->locator('[role="alert"]')->waitFor(['state' => 'visible']);
    }

    public function alertText(): string
    {
        return $this->page->locator('[role="alert"]')->textContent() ?? '';
    }

    public function alertIsSuccess(): bool
    {
        $class = $this->page->locator('[role="alert"]')->getAttribute('class') ?? '';
        return str_contains($class, 'success');
    }

    public function alertIsError(): bool
    {
        $class = $this->page->locator('[role="alert"]')->getAttribute('class') ?? '';
        return str_contains($class, 'error');
    }

    public function submitButtonText(): string
    {
        return $this->page->locator('button[type="submit"]')->textContent() ?? '';
    }

    public function submitButtonIsEnabled(): bool
    {
        return $this->page->locator('button[type="submit"]')->isEnabled();
    }

    public function emailValue(): string
    {
        return $this->page->locator('input[name="email"]')->inputValue();
    }

    public function repoValue(): string
    {
        return $this->page->locator('input[name="repo"]')->inputValue();
    }

    public function formIsVisible(): bool
    {
        return $this->page->locator('form#subscribeForm')->isVisible();
    }

    public function emailInputIsVisible(): bool
    {
        return $this->page->locator('input[name="email"]')->isVisible();
    }

    public function repoInputIsVisible(): bool
    {
        return $this->page->locator('input[name="repo"]')->isVisible();
    }
}
