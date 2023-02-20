<?php

declare(strict_types=1);

namespace BradieTilley\StoryBoard\Contracts;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * This object has a performer (authenticated user) that
 * can be set and retrieved at any point. A custom override
 * for the underlying `actingAs` logic may also be provided.
 *
 * This interface has no inheritance as the authenticated models
 * are resolved by either the test suite migration or by a story
 * action (occurs after inheritance).
 *
 * Therefore, running `->user()` will immediately attempt a login
 * of the provided user.
 */
interface WithPerformer
{
    /**
     * Specify what to do when the user is set
     */
    public static function actingAs(?Closure $actingAsCallback): void;

    /**
     * Alias of setUser()
     */
    public function user(?Authenticatable $user): static;

    /**
     * Set the user to perform this test as
     */
    public function setUser(?Authenticatable $user): static;

    /**
     * Get the user to perform this test as
     */
    public function getUser(): ?Authenticatable;
}
