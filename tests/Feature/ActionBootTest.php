<?php

use function BradieTilley\Stories\Helpers\story;
use BradieTilley\Stories\Story;
use Tests\Fixtures\AnExampleActionWithTraits;

test('traits on an action class are booted', function () {
    AnExampleActionWithTraits::$ran = [];

    Story::setInstance(story());
    story()->action(AnExampleActionWithTraits::class);

    expect(AnExampleActionWithTraits::$ran)->toBe([
        'bootTestBootableTrait',
        'invoke',
    ]);
});
