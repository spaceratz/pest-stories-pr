<?php

namespace BradieTilley\StoryBoard\Contracts;

interface WithName
{
    /**
     * Run when parent class is cloned; name may need updating?
     */
    public function __cloneName(): void;

    /**
     * Alias for setName()
     */
    public function name(string $name): static;

    /**
     * Set the name (or name fragment) of this story
     */
    public function setName(string $name): static;

    /**
     * Get the name (or name fragment) of this story
     */
    public function getName(): ?string;

    /**
     * Get the name (or name fragment) of this story.
     */
    public function getNameString(): string;

    /**
     * Inherit the name from parents
     */
    public function inheritName(): void;

    /**
     * Get full name, minus expectation
     * @return string 
     */
    public function getFullName(): string;

    /**
     * Get the name of this ancestory level
     */
    public function getLevelName(): string;
}
