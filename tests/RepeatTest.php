<?php

use BradieTilley\StoryBoard\Story;
use BradieTilley\StoryBoard\Story\Action;
use Illuminate\Support\Collection;

test('an action can be run once', function () {
    $run = Collection::make();

    Action::make('something')->as(fn () => $run[] = 'action');

    Story::make('story')
        ->can()
        ->assert(fn () => null)
        ->action(fn () => $run[] = 'action2')
        ->action('something')
        ->boot()
        ->perform();

    expect($run->toArray())->toBe([
        'action',
        'action2',
    ]);
});

test('an action can be run multiple times', function () {
    $run = Collection::make();

    Action::make('something')->as(fn () => $run[] = 'action')->repeat(3);

    Story::make('story')
        ->can()
        ->assert(fn () => null)
        ->action(fn () => $run[] = 'action2')
        ->action('something')
        ->boot()
        ->perform();

    expect($run->toArray())->toBe([
        'action',
        'action',
        'action',
        'action2',
    ]);
});

test('an action can be run zero times', function () {
    $run = Collection::make();

    Action::make('something')->as(fn () => $run[] = 'action')->repeat(0);

    Story::make('story')
        ->can()
        ->assert(fn () => null)
        ->action(fn () => $run[] = 'action2')
        ->action('something')
        ->boot()
        ->perform();

    expect($run->toArray())->toBe([
        'action2',
    ]);
});

test('an action can opt to not repeat (run once)', function () {
    $run = Collection::make();

    Action::make('something')->as(fn () => $run[] = 'action')->dontRepeat();

    Story::make('story')
        ->can()
        ->assert(fn () => null)
        ->action(fn () => $run[] = 'action2')
        ->action('something')
        ->boot()
        ->perform();

    expect($run->toArray())->toBe([
        'action',
        'action2',
    ]);
});
