# Open Questions & Assumptions

## Standards Alignment

1. Assumption: documentation was aligned to repository-level coding and PHPDoc conventions embedded in current project instructions.

- Why it matters: naming and example style consistency.
- Needed input: canonical, versioned Coding Standards Guide file path and PHPDoc guide artifact for explicit cross-reference.

## Deployment

1. Unknown: target deployment topology (VM, container, serverless) for host apps.

- Why it matters: final deployment and rollback runbooks differ.
- Needed input: official platform and release strategy.

2. Unknown: mandatory host app cache backend in production when OIDC is enabled.

- Why it matters: JWKS cache behavior and failure modes depend on cache durability.
- Needed input: required cache backend policy.

## API and Contracts

1. Assumption: Caronte API endpoint paths used in package API classes are authoritative.

- Why it matters: docs reflect current client code, not external server docs.
- Needed input: server-side API contract document version and backward compatibility guarantees.

2. Unknown: guaranteed schema for all Caronte error payloads and tenant selection conflict payloads.

- Why it matters: robust client-side error handling and integrator expectations.
- Needed input: documented JSON schema per endpoint and status code.

## Routes and Security

1. Assumption: package routes remain web-routed even for JSON clients.

- Why it matters: middleware stack, CSRF behavior, and response mode expectations.
- Needed input: whether dedicated API route registration is planned.

2. Unknown: long-term removal timeline for deprecated aliases:

- caronte.permissions config
- caronte:permissions:sync command
- caronte.app-token and caronte.app-permissions middleware aliases
- Why it matters: migration planning for integrators.
- Needed input: target major release and deprecation policy dates.

## Monitoring

1. Unknown: organization-standard alerts and SLOs for auth/authorization failures.

- Why it matters: actionable monitoring setup in host apps.
- Needed input: agreed thresholds for 401/403/error-rate/latency alerts.

2. Assumption: package relies on host logging and APM; no package-specific channel is required.

- Why it matters: troubleshooting ownership boundaries.
- Needed input: whether maintainers want package-specific log channel recommendations.

## Testing

1. Unknown: required minimum coverage target for this package.

- Why it matters: PR acceptance criteria and regression risk control.
- Needed input: expected percentage or critical-path coverage requirements.

2. Assumption: feature-level contract testing is the current preferred approach over granular unit tests for every support class.

- Why it matters: future test design consistency.
- Needed input: maintainer preference for expanding unit vs integration-style tests.
