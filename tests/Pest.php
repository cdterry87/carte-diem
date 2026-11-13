<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * The toast the last web request flashed, if any, as `['type' => ..., 'message' => ...]`.
 *
 * @return array{type: string, message: string}|null
 */
function flashedToast(): ?array
{
    /** @var array<string, mixed> $flash */
    $flash = session(SessionKey::FLASH_DATA, []);

    /** @var array{type: string, message: string}|null */
    return $flash['toast'] ?? null;
}

/**
 * Web conflicts send the person back with an error toast instead of failing.
 */
function expectConflictToast(?string $contains = null): void
{
    $toast = flashedToast();

    expect($toast)->not->toBeNull()
        ->and($toast['type'] ?? null)->toBe('error');

    if ($contains !== null) {
        expect($toast['message'] ?? '')->toContain($contains);
    }
}

/**
 * The props of an Inertia page response, or one of them by dot path.
 */
function pageProps(TestResponse $response, ?string $key = null): mixed
{
    $response->assertOk();
    /** @var array{props: array<string, mixed>} $page */
    $page = $response->viewData('page');

    return $key === null ? $page['props'] : data_get($page['props'], $key);
}
