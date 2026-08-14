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

$result = $policy->select($arms);

$result->selectedArm; // 'variant_b' — usually, but not always
$result->sample;      // 0.9412... the draw that won
$result->samples;     // every draw: ['variant_a' => 0.7318..., 'variant_b' => 0.9412...]
```

The result is deliberately stochastic. An arm that looks worse still gets picked
occasionally — that is the exploration that stops the bandit from locking onto
an early fluke.

Keeping the draws makes a decision explainable after the fact: log
`$result->samples` and you can replay why that arm won, without drawing again.
When you only need the key, use the shorthand:

```php
$policy->selectArm($arms); // 'variant_b'
```

There is no "was this exploration or exploitation?" flag, because Thompson
Sampling has no such mode — every pick is one draw per arm. If you want to know
whether the winner was the current front-runner, compare it yourself:

```php
$means = array_map(
    static fn (ArmState $s): float => $policy->posteriorMean($s->successes, $s->failures),
    $arms,
);

$frontRunner = array_search(max($means), $means, strict: true);
$exploredAway = $frontRunner !== $result->selectedArm;
```

### Run the loop

Selecting is one fifth of the job. The full cycle is
**select → execute → observe → update → persist**:

```php
// 1. select — the library decides, from state you hand it
$arms   = $yourStorage->loadArms();          // array<string, ArmState>
$result = $policy->select($arms);

// 2. execute — outside the library: serve the variant, call the provider…
$outcome = $yourSystem->run($result->selectedArm);

// 3. observe — reduce the outcome to a binary success or failure
$succeeded = $outcome->isSuccess();

// 4. update — immutable transition, always on the arm that played
$updated = $succeeded
    ? $arms[$result->selectedArm]->withSuccess()
    : $arms[$result->selectedArm]->withFailure();

// 5. persist — outside the library: yours to write (see below)
$yourStorage->save($result->selectedArm, $updated);
```

Three rules hold that loop together:

- **Only the arm that played is updated.** The others observed nothing.
- **An observation is binary.** This is the Bernoulli variant; threshold a
  continuous reward before feeding it in.
- **A late observation is still valid.** A conversion that lands a day later is
  applied when it arrives, to the arm's current state.

If your storage counts trials rather than failures, build the state directly:

```php
$state = ArmState::fromTrials(trials: 100, successes: 63); // 63 successes, 37 failures
$state->trials();                                          // 100
```

### Persist it yourself, concurrently

The library is **stateless**: the policy remembers nothing between calls,
`ArmState` is immutable, and nothing here touches storage. That is deliberate —
keys, transactions, serialization and increment semantics belong to your
application, not to a bandit.

Which leaves one mistake worth naming. Loading an `ArmState`, calling
`withSuccess()`, and writing both counts back is a read-modify-write: two
concurrent processes write the same value and one observation vanishes. Under
concurrency, increment in the store instead:

```sql
UPDATE arms SET successes = successes + 1 WHERE arm = :arm;
```

`withSuccess()` and `withFailure()` remain the right API in memory — in a single
process, a simulation, or a test.

Two things you do *not* need to protect against:

- **A stale read before deciding.** Choosing on state that is a few requests old
  slows convergence; it does not bias it. `select()` needs no lock.
- **A lost or duplicated observation.** At volume the posterior absorbs the
  noise. If rewards arrive asynchronously and can be replayed, deduplicate by
  decision id on your side.

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

**Invalid counts are rejected, not repaired.** A negative success or failure
count is a caller bug, never data: `ArmState` and every statistic throw
`InvalidArmStateException` rather than silently produce a wrong posterior. Every
exception in this package implements `BanditException`, so
`catch (BanditException $e)` catches all of them — and each one still extends its
natural SPL class.

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

## Upgrading from 0.1.0

`0.2.0` reshapes the public API. The maths are untouched: same algorithm, same
draws, same numbers.

| `0.1.0` | `0.2.0` |
|---|---|
| `$policy->selectBestArm($arms)` | `$policy->selectArm($arms)`, or `$policy->select($arms)->selectedArm` |
| `BanditPolicyInterface` declared the four statistics | it declares `select(array $arms): SelectionResult`; the statistics stay on `ThompsonSamplingPolicy` |
| type-hinting the interface to call `posteriorWeight()` | type-hint `ThompsonSamplingPolicy`, or declare your own application interface |
| `new ArmState($s->successes + 1, $s->failures)` | `$s->withSuccess()` |
| `new ArmState($successes, $trials - $successes)` | `ArmState::fromTrials($trials, $successes)` |
| negative counts were clamped to zero | they throw `InvalidArmStateException` |

`selectBestArm()` still works in `0.2.0`, deprecated, and is removed in `0.3.0`.
The name promised an `argmax`; what it returns is a posterior draw.

## Testing

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, max level
```

## License

MIT. See [LICENSE](LICENSE).
