<?php

use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsManager;

/*
|--------------------------------------------------------------------------
| The settings() helper
|--------------------------------------------------------------------------
|
| The helper is nothing but an overload on the shape of its arguments, so it
| is tested against a mocked manager: what matters is which method a given
| call shape ends up on, not what that method then does.
|
| It is registered through composer's "files" autoload rather than by the
| service provider, so it exists here even though tests/Unit never boots the
| provider - which is the whole point of registering it that way.
|
*/

beforeEach(function () {
    $this->manager = Mockery::mock(SettingsManager::class);

    app()->instance(SettingsManager::class, $this->manager);
});

it('is loaded before any service provider boots', function () {
    expect(function_exists('settings'))->toBeTrue();
});

it('guards against a colliding helper from another package', function () {
    /*
     * Without the function_exists() guard, two packages both defining
     * settings() would fatal at autoload time instead of one of them simply
     * losing.
     */
    expect(file_get_contents(__DIR__.'/../../helpers/helpers.php'))
        ->toContain("function_exists('settings')");
});

it('returns the manager itself when called with nothing', function () {
    expect(settings())->toBe($this->manager);
});

it('reads one setting when given one key', function () {
    $this->manager->shouldReceive('get')->with('a')->once()->andReturn('A');

    expect(settings('a'))->toBe('A');
});

it('reads many settings when given a list of keys', function () {
    $this->manager
        ->shouldReceive('get')->with(['a', 'b'])->once()
        ->andReturn(new Collection(['a' => 'A', 'b' => 'B']));

    expect(settings(['a', 'b'])->toArray())->toBe(['a' => 'A', 'b' => 'B']);
});

it('writes many settings when given a map of pairs', function () {
    $this->manager->shouldReceive('set')->with(['a' => 'A', 'b' => 'B'])->once();
    $this->manager->shouldReceive('get')->never();

    settings(['a' => 'A', 'b' => 'B']);
});

it('writes one setting when given a key and a value', function () {
    $this->manager->shouldReceive('set')->with('a', 'A')->once();

    settings('a', 'A');
});

it('writes a null, which the manager treats as a delete', function () {
    $this->manager->shouldReceive('set')->with('a', null)->once();

    settings('a', null);
});

it('tells a map from a list by its keys', function ($argument, string $expected) {
    $this->manager->shouldReceive($expected)->with($argument)->once();

    settings($argument);
})->with([
    'a list' => [['a', 'b'], 'get'],
    'a list of one' => [['a'], 'get'],
    'a map' => [['a' => 'A'], 'set'],
    'a map with gaps in its integer keys' => [[1 => 'A'], 'set'],
    'a map with integer keys out of order' => [[1 => 'A', 0 => 'B'], 'set'],
    'nothing at all' => [[], 'get'],
]);

it('reads rather than writes when a map has a single key of "0"', function () {
    /*
     * PHP casts the string key "0" to the integer 0, which makes
     * ['0' => 'theme'] indistinguishable from ['theme'] - so it is read as a
     * multi-get of the key "theme". Nothing can be done about it inside the
     * helper; write such a key through set() instead.
     */
    $this->manager->shouldReceive('get')->with(['0' => 'theme'])->once();
    $this->manager->shouldReceive('set')->never();

    settings(['0' => 'theme']);
});
