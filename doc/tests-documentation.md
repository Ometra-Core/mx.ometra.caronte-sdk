# Tests Documentation

## 1. Test Framework and Execution

- Framework: PHPUnit (10 or 11 supported by composer constraints)
- Testbench: orchestra/testbench for package testing
- Test suite configured in phpunit.xml.dist:
    - tests/Feature

Run commands:

- vendor/bin/phpunit
- vendor/bin/phpunit tests/Feature/ConfigurationValidationTest.php

## 2. Test Structure

- Base class: Tests\TestCase
    - Registers package providers
    - Sets package config defaults
    - Includes token helpers for user, application, group, and protected API tokens
- Alternate base: Tests\DisabledManagementTestCase
    - Same setup with management disabled

Main feature files:

- RouteRegistrationTest and RouteRegistrationWhenDisabledTest
- MiddlewareBehaviorTest
- AuthContractTest
- ManagementUiTest and ManagementUiWhenDisabledTest
- CommandBehaviorTest
- ConfigurationValidationTest
- ResolvedOpenQuestionsTest

## 3. Coverage Overview

Well covered:

- Route registration and management enable/disable behavior
- Middleware behavior for:
    - user session auth
    - role checks
    - application/group token context
    - protected API token and scope checks
- Command integration behavior (HTTP fakes and request assertions)
- Authentication contract behavior against Caronte API endpoints
- Configuration validation (tenancy mode, required config, role normalization)

Moderate coverage:

- Management UI rendering (Blade and Inertia variants)
- Local user cache interaction helpers

Lower coverage / gaps:

- Dedicated unit tests for every support class are limited
- End-to-end host integration scenarios are not part of package tests
- OIDC edge cases are partially covered indirectly; deeper callback failure matrices may need expansion

## 4. Test Conventions and Patterns

- Use HTTP::fake for Caronte API boundary testing.
- Use explicit assertions on outgoing headers:
    - X-Application-Token
    - X-Group-Token
    - X-Tenant-Id
- Prefer named route assertions and middleware behavior assertions.
- Validate deprecated aliases while keeping migration path explicit.

## 5. Adding New Tests

Recommended pattern:

1. Add new feature tests under tests/Feature.
2. Extend Tests\TestCase unless management disabled behavior is required.
3. Use helper token factories in TestCase to avoid duplicated JWT setup.
4. Mock Caronte external calls with HTTP::fake and assert payload/header contracts.
5. Keep test names behavior-focused and long-form for readability.

## 6. Quality Gate Suggestion

For package changes affecting auth, middleware, or API contracts:

- Run full feature suite before merge.
- Add at least one regression test per bugfix.
- Include scenario tests for both JSON and redirect behaviors when controller responses depend on request mode.
