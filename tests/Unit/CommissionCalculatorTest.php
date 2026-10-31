<?php

use App\Billing\CommissionCalculator;
use App\Models\Vendor;
use App\Support\Money;

it('splits an amount into commission and vendor earnings', function () {
    $split = (new CommissionCalculator(1000))->split(Money::of(10000, 'USD'), 1000);

    expect($split->commission->amount)->toBe(1000)
        ->and($split->vendorEarnings->amount)->toBe(9000)
        ->and($split->rateBps)->toBe(1000);
});

it('rounds commission half up to the nearest cent', function (int $gross, int $rateBps, int $expectedCommission) {
    $split = (new CommissionCalculator(1000))->split(Money::of($gross, 'USD'), $rateBps);

    expect($split->commission->amount)->toBe($expectedCommission);
})->with([
    '10% of 19.99 = 1.999 -> 2.00' => [1999, 1000, 200],
    '10% of 0.05 = 0.005 -> 0.01' => [5, 1000, 1],
    '10% of 0.04 = 0.004 -> 0.00' => [4, 1000, 0],
    '12.5% of 0.99 = 0.12375 -> 0.12' => [99, 1250, 12],
    '15% of 33.33 = 4.9995 -> 5.00' => [3333, 1500, 500],
    '7.25% of 100.00 = 7.25' => [10000, 725, 725],
]);

it('never loses or creates a cent', function (int $gross, int $rateBps) {
    $split = (new CommissionCalculator(1000))->split(Money::of($gross, 'USD'), $rateBps);

    expect($split->commission->plus($split->vendorEarnings)->amount)->toBe($gross);
})->with(function () {
    mt_srand(42);

    return array_map(fn () => [mt_rand(0, 10_000_000), mt_rand(0, 10_000)], range(1, 25));
});

it('handles the 0% and 100% edges', function () {
    $calculator = new CommissionCalculator(1000);

    expect($calculator->split(Money::of(1234, 'USD'), 0)->commission->amount)->toBe(0)
        ->and($calculator->split(Money::of(1234, 'USD'), 10_000)->vendorEarnings->amount)->toBe(0);
});

it('uses the vendor rate when set and the default otherwise', function () {
    $calculator = new CommissionCalculator(1000);

    expect($calculator->rateFor(new Vendor(['commission_rate_bps' => 1500])))->toBe(1500)
        ->and($calculator->rateFor(new Vendor(['commission_rate_bps' => null])))->toBe(1000)
        ->and($calculator->rateFor(new Vendor(['commission_rate_bps' => 0])))->toBe(0);
});

it('rejects rates outside 0..10000 bps', function (int $rate) {
    (new CommissionCalculator(1000))->split(Money::of(100, 'USD'), $rate);
})->with([-1, 10_001])->throws(InvalidArgumentException::class);

it('rejects negative amounts', function () {
    (new CommissionCalculator(1000))->split(Money::of(-100, 'USD'), 1000);
})->throws(InvalidArgumentException::class);
