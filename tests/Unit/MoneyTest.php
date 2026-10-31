<?php

use App\Support\Money;

it('adds and subtracts amounts in the same currency', function () {
    $a = Money::of(1050, 'usd');
    $b = Money::of(275, 'USD');

    expect($a->plus($b)->amount)->toBe(1325)
        ->and($a->minus($b)->amount)->toBe(775)
        ->and($a->plus($b)->currency)->toBe('USD');
});

it('multiplies by a quantity', function () {
    expect(Money::of(333, 'USD')->times(3)->amount)->toBe(999);
});

it('is immutable', function () {
    $price = Money::of(500, 'USD');
    $price->plus(Money::of(100, 'USD'));

    expect($price->amount)->toBe(500);
});

it('refuses to mix currencies', function () {
    Money::of(100, 'USD')->plus(Money::of(100, 'EUR'));
})->throws(InvalidArgumentException::class, 'Currency mismatch');

it('rejects invalid currency codes', function () {
    Money::of(100, 'US');
})->throws(InvalidArgumentException::class);

it('formats without floating point', function (int $amount, string $expected) {
    expect(Money::of($amount, 'USD')->format())->toBe($expected);
})->with([
    [0, '0.00'],
    [5, '0.05'],
    [1999, '19.99'],
    [100000, '1000.00'],
    [-1205, '-12.05'],
]);

it('serializes to a stable JSON shape', function () {
    expect(json_encode(Money::of(1999, 'usd')))
        ->toBe('{"amount":1999,"currency":"USD","formatted":"19.99"}');
});
