<?php

declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Tests\E2E\Pages\SubscribePage;

/**
 * E2E tests for the subscription flow on public/subscribe.html.
 *
 * Tests that call the API's subscribe endpoint may trigger a real GitHub API
 * validation from the server. Set GITHUB_TOKEN in the server environment for
 * reliable CI runs. Use APP_URL to point at the running stack.
 */
final class SubscribeFlowTest extends AbstractE2ETestCase
{
    private const KNOWN_REPO   = 'octocat/Hello-World';
    private const INVALID_REPO = 'not-valid';
    private const MISSING_REPO = 'this-user-xyz-404/this-repo-xyz-404';

    private SubscribePage $subscribePage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscribePage = new SubscribePage($this->page);
    }

    // ── Page load ─────────────────────────────────────────────────────────────

    #[Test]
    public function itLoadsTheSubscribePageWithCorrectTitle(): void
    {
        $this->visit('/subscribe.html');

        self::assertStringContainsString(
            'Subscribe',
            $this->page->title(),
        );
    }

    #[Test]
    public function itShowsTheSubscribeFormWithAllInputs(): void
    {
        $this->visit('/subscribe.html');

        self::assertTrue($this->subscribePage->formIsVisible());
        self::assertTrue($this->subscribePage->emailInputIsVisible());
        self::assertTrue($this->subscribePage->repoInputIsVisible());
        self::assertSame('Subscribe', trim($this->subscribePage->submitButtonText()));
    }

    #[Test]
    public function itShowsTheSubmitButtonAsEnabledByDefault(): void
    {
        $this->visit('/subscribe.html');

        self::assertTrue($this->subscribePage->submitButtonIsEnabled());
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    #[Test]
    public function itShowsSuccessAlertAfterValidSubscription(): void
    {
        $this->visit('/subscribe.html');

        $this->subscribePage->fillEmail($this->uniqueEmail());
        $this->subscribePage->fillRepo(self::KNOWN_REPO);
        $this->subscribePage->submit();

        $this->subscribePage->waitForAlert();

        self::assertTrue($this->subscribePage->alertIsSuccess());
        self::assertNotEmpty($this->subscribePage->alertText());
    }

    #[Test]
    public function itResetsTheFormAfterSuccessfulSubscription(): void
    {
        $this->visit('/subscribe.html');

        $this->subscribePage->fillEmail($this->uniqueEmail('reset'));
        $this->subscribePage->fillRepo(self::KNOWN_REPO);
        $this->subscribePage->submit();

        $this->subscribePage->waitForAlert();

        self::assertSame('', $this->subscribePage->emailValue());
        self::assertSame('', $this->subscribePage->repoValue());
    }

    #[Test]
    public function itReEnablesSubmitButtonAfterSubmission(): void
    {
        $this->visit('/subscribe.html');

        $this->subscribePage->fillEmail($this->uniqueEmail('reenable'));
        $this->subscribePage->fillRepo(self::KNOWN_REPO);
        $this->subscribePage->submit();

        $this->subscribePage->waitForAlert();

        self::assertTrue($this->subscribePage->submitButtonIsEnabled());
        self::assertSame('Subscribe', trim($this->subscribePage->submitButtonText()));
    }

    // ── Validation errors ─────────────────────────────────────────────────────

    #[Test]
    public function itShowsErrorAlertWhenEmailIsEmpty(): void
    {
        $this->visit('/subscribe.html');

        $this->subscribePage->fillRepo(self::KNOWN_REPO);
        $this->subscribePage->submit();

        $this->subscribePage->waitForAlert();

        self::assertTrue($this->subscribePage->alertIsError());
    }

    #[Test]
    public function itShowsErrorAlertWhenRepoIsEmpty(): void
    {
        $this->visit('/subscribe.html');

        $this->subscribePage->fillEmail($this->uniqueEmail('no-repo'));
        $this->subscribePage->submit();

        $this->subscribePage->waitForAlert();

        self::assertTrue($this->subscribePage->alertIsError());
    }

    #[Test]
    public function itShowsErrorAlertForInvalidRepoFormat(): void
    {
        $this->visit('/subscribe.html');

        $this->subscribePage->fillEmail($this->uniqueEmail('bad-format'));
        $this->subscribePage->fillRepo(self::INVALID_REPO);
        $this->subscribePage->submit();

        $this->subscribePage->waitForAlert();

        self::assertTrue($this->subscribePage->alertIsError());
    }

    // ── Server-side errors ────────────────────────────────────────────────────

    #[Test]
    public function itShowsErrorAlertWhenRepoDoesNotExistOnGitHub(): void
    {
        $this->visit('/subscribe.html');

        $this->subscribePage->fillEmail($this->uniqueEmail('no-gh-repo'));
        $this->subscribePage->fillRepo(self::MISSING_REPO);
        $this->subscribePage->submit();

        $this->subscribePage->waitForAlert();

        self::assertTrue($this->subscribePage->alertIsError());
    }
}
