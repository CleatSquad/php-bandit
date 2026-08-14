# Changelog

All notable changes to `cleatsquad/php-bandit` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.1.0]: https://github.com/CleatSquad/php-bandit/releases/tag/v0.1.0
