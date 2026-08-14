# PHP Bandit

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg)](composer.json)

Beta-Bernoulli Thompson Sampling for multi-armed bandit problems, in
dependency-free PHP.

You have several options and no idea which performs best. Testing them evenly
wastes traffic on the losers; committing early to the leader may commit to a
fluke. Thompson Sampling resolves that trade-off by drawing from each option's
posterior belief and picking the winner of that draw — good options get chosen
more often, uncertain ones keep getting explored.

## Installation

```bash
composer require cleatsquad/php-bandit
```

Requires PHP 8.2 or later. No runtime dependencies.

## Usage

### Pick an option

```php
use CleatSquad\Bandit\ArmState;
use CleatSquad\Bandit\ThompsonSamplingPolicy;

$policy = new ThompsonSamplingPolicy();

$arms = [
    'variant_a' => new ArmState(successes: 12, failures: 4),
    'variant_b' => new ArmState(successes: 30, failures: 1),
];

$policy->selectBestArm($arms); // usually 'variant_b', sometimes 'variant_a'
```

The result is deliberately stochastic. An arm that looks worse still gets picked
occasionally — that is the exploration that stops the bandit from locking onto
an early fluke.

### Inspect the posterior

```php
$policy->posteriorMean(10, 2);     // 0.846 — expected success rate
$policy->posteriorVariance(10, 2); // how unsure that estimate is
$policy->posteriorWeight(10, 2);   // 0.0..1.0 confidence, 0 when uninformed
$policy->sample(10, 2);            // one random draw from Beta(11, 3)
```

### Reproducible runs

```php
$policy = ThompsonSamplingPolicy::withSeed(42);
$policy->sample(5, 2); // same value on every run, for tests and simulations
```

## Design notes

**Beta(1,1) prior.** Successes and failures are offset by an uninformative
prior, so an arm with no data yet behaves as a coin flip rather than dividing
by zero.

**Gamma draws via Marsaglia & Tsang (2000).** Constant-time rejection sampling,
valid for shape ≥ 1 — which the Beta(1,1) prior guarantees by construction.
Beta samples are then formed as `X / (X + Y)` from two Gamma draws.

**Native randomness.** Uses PHP's `\Random\Randomizer`. Inject your own engine,
or use `withSeed()` for a Mt19937 engine with a fixed seed.

**Negative inputs are clamped.** Counts below zero are treated as zero rather
than corrupting the posterior.

## Statistical validation

Sample distributions were checked against the exact Beta CDF with a
Kolmogorov-Smirnov test across several posteriors — uninformed, balanced,
skewed, and highly concentrated — over independent seeds, alongside moment
matching on the underlying Gamma draws. See the test suite.

## When to use it

Good fits: A/B and multivariate testing, traffic allocation, ranking candidate
strategies, model or provider selection, any explore-versus-exploit choice with
a binary outcome.

Poor fits: rewards that are not success/failure (this is the Bernoulli variant),
or a setting where a single decision must be reproducible without a fixed seed.

## Testing

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, max level
```

## License

MIT. See [LICENSE](LICENSE).
