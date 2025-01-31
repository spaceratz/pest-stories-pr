<?php

declare(strict_types=1);

namespace BradieTilley\Stories;

use BradieTilley\Stories\PendingCalls\PendingActionCall;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Pest\Expectations\HigherOrderExpectation;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;

class Story
{
    /**
     * @var array<int, Action>
     */
    protected array $actions = [];

    /**
     * @var array<mixed>
     */
    protected array $data = [];

    protected static ?Story $instance = null;

    /**
     * (Internal function) Set the current story that is being invoked
     */
    public static function setInstance(?Story $story): void
    {
        static::$instance = $story;
    }

    /**
     * Get the current story instance that's being invoked
     */
    public static function getInstance(): ?Story
    {
        return static::$instance;
    }

    /**
     * Add an action to this story
     *
     * @param  array<string, mixed>  $arguments
     */
    public function action(string|Closure|Action|PendingActionCall $action, array $arguments = [], string $variable = null, bool $dataset = false): static
    {
        $action = Action::parse($action);
        $action = Action::resolve($action);

        $action = $action->fresh($this);

        if ($dataset) {
            $action->dataset();
        }

        $action->run($this, arguments: $arguments, variable: $variable);

        return $this;
    }

    /**
     * Add an expectation to this story
     */
    public function expects(string|Closure $expect): HigherOrderExpectation
    {
        if (is_string($expect)) {
            /** @phpstan-ignore-next-line */
            return expect($this)->getData($expect);
        }

        /** @phpstan-ignore-next-line */
        return expect($this)->callCallback($expect);
    }

    /**
     * Get a shared variable in this story
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * Set a shared variable in this story
     */
    public function setData(string $key, mixed $value): static
    {
        Arr::set($this->data, $key, $value);

        return $this;
    }

    /**
     * Check the existence of a shared variable in this story
     */
    public function hasData(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * Get all shared variables in this story
     *
     * @return array<string, mixed>
     */
    public function allData(): array
    {
        return $this->data;
    }

    /**
     * Get the dataset that was provided, if any.
     *
     * @return array<int, mixed>
     */
    public function getDataset(): array
    {
        /** @var array<int, mixed> $dataset */
        $dataset = $this->getTest()->providedData();

        return $dataset;
    }

    /**
     * Call the given callback with dependency injection
     *
     * @param  array<string, mixed>  $additional
     */
    public function callCallback(callable $callback = null, array $additional = []): mixed
    {
        if ($callback === null) {
            return null;
        }

        return Container::getInstance()->call($callback, $this->getCallbackArguments($additional));
    }

    /**
     * Get a list of arguments that may be injected into Closure callbacks
     *
     * @param  array<string, mixed>  $additional
     * @return array<string, mixed>
     */
    public function getCallbackArguments(array $additional = []): array
    {
        $arguments = array_replace(
            [
                'story' => $this,
                'test' => $this->getTest(),
            ],
            $this->allData(),
            $additional,
        );

        return $arguments;
    }

    /**
     * Get the test case
     */
    public function getTest(): TestCase
    {
        /** @phpstan-ignore-next-line */
        return TestSuite::getInstance()->test;
    }
}
