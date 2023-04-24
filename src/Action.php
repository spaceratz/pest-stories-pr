<?php

declare(strict_types=1);

namespace BradieTilley\Stories;

use BradieTilley\Stories\Concerns\Binds;
use BradieTilley\Stories\Concerns\Events;
use BradieTilley\Stories\Concerns\Repeats;
use BradieTilley\Stories\Concerns\Times;
use BradieTilley\Stories\Contracts\Deferred;
use BradieTilley\Stories\Exceptions\StoryActionInvalidException;
use BradieTilley\Stories\PendingCalls\PendingCall;
use BradieTilley\Stories\Repositories\Actions;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionProperty;

/**
 * @method static static make(string $name = null, ?Closure $callback = null, string $variable = null)
 */
class Action
{
    use Repeats;
    use Times;
    use Events;
    use Binds;

    /**
     * The name of the action
     */
    protected string $name;

    /**
     * The name of the variable
     */
    protected string $variable;

    /**
     * The callback to run, if not invokable via __invoke
     */
    protected ?Closure $callback = null;

    /**
     * @var Collection<int, Action>
     */
    protected Collection $actions;

    public function __construct(string $name = null, ?Closure $callback = null, string $variable = null)
    {
        if (! $this->initializedProperty('name')) {
            $this->name = $name ?? self::getRandomName();
        }

        if (! $this->initializedProperty('variable')) {
            $this->variable = $variable ?? $this->name;
        }

        $this->callback = $callback;
        $this->actions = Collection::make();
        $this->remember();
        $this->boot();
    }

    /**
     * Boot this action and all of its traits
     */
    public function boot(): void
    {
        foreach (class_uses_recursive($this) as $trait) {
            $method = 'boot'.Str::afterLast($trait, '\\');

            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    /**
     * Determine if the property has been initialized yet
     */
    protected function initializedProperty(string $property): bool
    {
        return (new ReflectionProperty($this, $property))->isInitialized($this);
    }

    /**
     * Statically create this action.
     * Note: If the action is a Deferred action (see interface) then
     * the action returned is a pending call.
     *
     * @return PendingCall<static>|static
     */
    public static function make(): PendingCall|static
    {
        /** @phpstan-ignore-next-line */
        $action = new static(...func_get_args());

        if ($action instanceof Deferred) {
            /** @var PendingCall<static> $action */
            $action = new PendingCall($action);
        }

        return $action;
    }

    /**
     * Statically create and defer the building of this action
     * Note: Only a `PendingCall` instance is returned, never `static`. The `static` return typehint is added for IDEs that can't compute templates.
     *
     * @phpstan-ignore-next-line
     *
     * @return PendingCall<static>|static
     */
    public static function defer(): PendingCall
    {
        /** @phpstan-ignore-next-line */
        $action = static::make(...func_get_args());
        /** @var static $action */

        return new PendingCall($action);
    }

    /**
     * Get the name of this action object
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get a random name to use for this action
     */
    public static function getRandomName(): string
    {
        return static::class.'@'.Str::random(8);
    }

    /**
     * Store this action in the action repository so that
     * it can be referenced later
     */
    public function remember(): static
    {
        Actions::store($this->name, $this);

        return $this;
    }

    /**
     * Set the callback to invoke when running this action
     */
    public function as(?Closure $callback = null): static
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Set the name of this action
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Require this action to be run as part of this action.
     */
    public function action(string|Closure|Action $action): static
    {
        $action = Action::parse($action);
        $this->actions->push($action);

        return $this;
    }

    /**
     * Clone this action call for the given story.
     */
    public function fresh(Story $story): static
    {
        return clone $this;
    }

    /**
     * Run the action against the given story
     *
     * @param  array<string, mixed>  $arguments
     */
    private function process(Story $story, array $arguments = [], string $variable = null): void
    {
        $this->callbackRun('before');

        /**
         * If this action calls for other actions to be run, then run those
         * other actions first.
         */
        $this->actions->each(fn (Action $action) => $action->fresh($story)->run($story));

        /**
         * Default to use the __invoke method as the callback
         *
         * @var callable $callback
         */
        $callback = [$this, '__invoke'];

        /**
         * If this action has a Closure callback then we'll use that
         * instead of the __invoke method
         */
        if ($this->callback !== null) {
            $callback = $this->bindToPreferred($this->callback);
        }

        /**
         * Call the callback (__invoke method or Closure callback)
         * with the story's arguments
         */
        $value = $story->callCallback($callback, [
            'action' => $this,
        ] + $arguments);

        /**
         * Record the value returned from either the __invoke
         * method or the Closure callback against the story using
         * the variable specified with this action.
         */
        $variable ??= $this->getVariable();
        $story->setData($variable, $value);

        $this->callbackRun('after');
    }

    /**
     * Run the action against the given story, with
     * repeating, etc.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function run(Story $story, array $arguments = [], string $variable = null): void
    {
        if ($this->hasTimer()) {
            $this->timer()->start();
        }

        while ($this->repeats()) {
            $this->repeatsIncrement();

            $this->process($story, arguments: $arguments, variable: $variable);
        }

        if ($this->hasTimer()) {
            $this->timer()->end()->check();
        }
    }

    /**
     * Convert the given action into an ActionCall.
     *
     * string - lookup Action of the same name
     * Closure - create Action for this closure
     * ActionCall - returns itself
     */
    public static function parse(string|Closure|Action|PendingCall $action): Action
    {
        if ($action instanceof PendingCall) {
            $action = $action->invokePendingCall();

            if (! $action instanceof Action) {
                throw StoryActionInvalidException::make(get_class($action));
            }
        }

        if (is_string($action) && class_exists($action)) {
            $action = Container::getInstance()->make($original = $action);

            if (! $action instanceof Action) {
                throw StoryActionInvalidException::make($original);
            }
        }

        if (is_string($action)) {
            $action = Actions::fetch($action);
        }

        if ($action instanceof Closure) {
            $action = new Action('inline@'.Str::random(8), $action);
        }

        return $action;
    }

    /**
     * Get the name of the variable to use when storing the
     * action's result against the story
     */
    public function getVariable(): string
    {
        return $this->variable;
    }

    /**
     * Set the name of the variable to use when storing the
     * action's result against the story
     */
    public function variable(string $variable): static
    {
        $this->variable = $variable;

        return $this;
    }
}
