# Changelog

All notable changes to `cleatsquad/php-bandit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - Unreleased

Quality and hardening. The API reaches the shape it is meant to freeze at
`1.0.0`, and the claims the README makes about it are now backed by tests that
would fail if they stopped being true.

### Added

- Regret simulation: a full 20,000-round bandit episode against known
  conversion rates, asserting that cumulative regret grows sublinearly and that
  the second half of a run costs less than the first.
- Property-based tests, on generated rather than hand-picked cases, for the
  invariants of `ArmState`, the posterior statistics and a selection.
- Empirical variance matching against `posteriorVariance()`, on a relative
  tolerance. Matching the mean alone would pass a sampler stuck on the mean.
- `Exception\InvalidSelectionException`, thrown when a `SelectionResult` is
  built from a decision that could not have happened.
- Mutation testing with Infection and benchmarks with PHPBench, both installed
  from `tools/` by `composer mutation` and `composer bench` rather than from
  `require-dev`, so the PHP 8.2-8.5 matrix stays installable.
- PHP 8.5 in the test matrix.
- README sections on statistical validation, measured performance and the
  exception hierarchy.

### Changed

- **Breaking.** Uniform draws are taken over `2^53` instead of `PHP_INT_MAX`.
  The top of the range used to round to exactly `1.0` in double precision,
  which collapsed a Box-Muller normal draw to zero — roughly one draw in `9e18`,
  harmless in aggregate but outside the interval the sampler documents. Drawn
  sequences change for a given seed; distributions do not.
- `SelectionResult` validates its own bookkeeping: non-empty samples, a selected
  arm that was drawn, and a `sample` equal to that arm's draw. The winning draw
  is deliberately not required to be the largest — the type is shared by every
  policy, and only Thompson Sampling picks the maximum.
- `select()` opens its comparison on the first arm's draw instead of on a
  sentinel `-1.0` that every draw was assumed to beat. Same decision, including
  which arm wins a tie; one magic value less.

### Removed

- **Breaking.** `selectBestArm()`, deprecated since `0.2.0`. Use `selectArm()`.

## [0.2.0] - 2026-08-14

Public API only. The algorithm is untouched: same draws, same numbers, same
Kolmogorov-Smirnov validation. `0.x` is exactly the room this release uses to
correct the shape of the API before `1.0.0`.

### Added

- `SelectionResult`, returned by every decision: `selectedArm`, the winning
  `sample`, and `samples` for every candidate arm. Decisions are now
  explainable without drawing again.
- `select(array $arms): SelectionResult`, the single method of the reshaped
  `BanditPolicyInterface`.
- `selectArm()`, shorthand returning the winning key alone.
- `ArmState::fromTrials()`, for stores that count trials rather than failures.
- `ArmState::withSuccess()`, `ArmState::withFailure()` and `ArmState::trials()`.
- `Exception\BanditException`, a marker implemented by every exception thrown
  here, alongside `InvalidArmStateException` and `EmptyArmSetException`. Each one
  still extends its natural SPL class, so existing catches keep working.
- README sections for the full **select → execute → observe → update → persist**
  loop, and for persistence and concurrency.

### Changed

- **Breaking.** `BanditPolicyInterface` no longer declares `posteriorMean()`,
  `posteriorVariance()`, `posteriorWeight()` or `sample()`. Those describe a Beta
  posterior — a property of this policy, not of every policy — and remain public
  and unchanged on `ThompsonSamplingPolicy`. Type-hint the class, or your own
  application interface.
- **Breaking.** `ArmState` rejects negative counts with
  `InvalidArmStateException` instead of accepting them.
- **Breaking.** The four statistics reject negative counts instead of clamping
  them to zero. A clamped negative produced a silently wrong posterior.
- `select()` throws `EmptyArmSetException` where `selectBestArm()` threw a plain
  `\InvalidArgumentException`. The new exception extends it, so existing catches
  still match.

### Deprecated

- `selectBestArm()`, now a thin alias of `selectArm()`. Removed in `0.3.0`: the
  name promised an `argmax`, while the pick is a posterior draw.

### Removed

- The `max(0, …)` clamp in the posterior parameters, replaced by validation at
  the boundary.
- The `max(0.0, min(1.0, …))` clamp in `posteriorWeight()`. Under the Beta(1,1)
  prior the posterior variance peaks at the prior's own `1/12`, so the result is
  in `[0, 1)` by construction. `PosteriorBoundsTest` proves the bounds are
  unreachable over a wide grid; the clamp was removed only once that test was
  green.

### Notes

- Selection is now covered by behavioural tests — dominance, exploration,
  uniformity when uninformed, key preservation, determinism under a seed —
  rather than by asserting which arm one fixed seed happened to return.

## [0.1.0] - 2026-08-14

Initial release. Published as `0.x` on purpose: the algorithm is validated, but
the shape of the public API has not yet been exercised by real users, and `0.x`
leaves room to correct it without a major release.

### Added

- `ThompsonSamplingPolicy`, a Beta-Bernoulli Thompson Sampling policy with no
  runtime dependencies.
- `BanditPolicyInterface`, the contract implemented by bandit policies.
- `ArmState`, an immutable success/failure pair for one arm.
- `selectBestArm()`, drawing from each arm's posterior and returning the winner.
- `posteriorMean()`, `posteriorVariance()` and `posteriorWeight()` for
  inspecting a posterior without drawing from it.
- `sample()`, one draw from `Beta(1 + successes, 1 + failures)`, built from two
  Gamma draws using Marsaglia & Tsang (2000) rejection sampling.
- `withSeed()`, returning a policy backed by a seeded Mt19937 engine for
  reproducible tests and simulations.

### Notes

- Draw distributions are verified against the exact Beta CDF with a
  Kolmogorov-Smirnov test over five posteriors, alongside moment matching. The
  checks use fixed seeds and are therefore deterministic.
- Negative success or failure counts are clamped to zero rather than corrupting
  the posterior.

[0.2.0]: https://github.com/CleatSquad/php-bandit/releases/tag/v0.2.0
[0.1.0]: https://github.com/CleatSquad/php-bandit/releases/tag/v0.1.0
