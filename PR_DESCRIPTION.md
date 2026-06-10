# Release v4.5.1 - Bug Fix: Inertia Response Handling

## Motivation & Context

When Caronte returns an error response with validation errors and a `forwardUrl` to redirect to, the response handler (`CaronteResponse::redirect()`) was losing the session data before Inertia could process it. This occurred specifically in Inertia requests where the response needed to be wrapped with `Inertia::location()`.

The bug manifested when:

- An error response (HTTP 4xx/5xx) was generated via `CaronteResponse::unprocessable()` or similar methods
- A `forwardUrl` was specified to redirect the user after handling
- The request was an Inertia request (detected via `X-Inertia` header)

In these cases, validation errors and form input would be lost, resulting in a poor user experience where users couldn't see validation messages after redirect.

## Summary of Changes

### Files Modified

1. **src/Support/CaronteResponse.php** (Main bug fix)
    - Restructured the `redirect()` method to properly sequence response handling
    - Moved the Inertia location wrapping to occur **after** all response modifications (error data, success messages, input preservation)
    - Now passes the complete, properly-configured response object to `Inertia::location()` instead of a bare destination URL

2. **tests/Feature/MiddlewareBehaviorTest.php** (Test coverage)
    - Added route for testing Inertia error forward scenarios: `POST /_caronte/inertia-forward-error`
    - Added feature test `test_inertia_forward_error_preserves_errors_and_input()` that validates:
        - Error response status codes are correct (409 → 422)
        - Validation errors are preserved in session
        - Old input is preserved in session
        - Sensitive fields (passwords) are excluded from old input
        - Inertia location header is properly set with the forward URL

## Testing Checklist

- ✅ All existing tests pass: `composer test`
- ✅ New test case validates fix: `MiddlewareBehaviorTest::test_inertia_forward_error_preserves_errors_and_input()`
- ✅ No PHP syntax errors: `vendor/bin/phpstan analyse src/`
- ✅ CI/CD pipeline green (if applicable)
- ✅ Manual validation: error responses with validation errors now preserve session data in Inertia requests

## Risk & Impact Assessment

**Risk Level:** Low

- This is a targeted bug fix with no breaking changes
- The fix only affects error responses being forwarded in Inertia requests
- Existing behavior for non-Inertia requests or non-forwarded responses is unchanged
- The change is backward-compatible; all existing consumer code continues to work

**Impact:**

- Improves user experience in Inertia-based applications using Caronte for authentication
- Applications relying on validation error preservation through redirects will now work correctly
- No impact on applications using session-based or API-only error handling

## Related Issues & Documentation

- See [CHANGELOG.md](CHANGELOG.md#451---2026-06-10) for complete v4.5.1 release entry
- See [RELEASE_NOTES.md](RELEASE_NOTES.md) for release highlights and usage guidance
- See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) — no breaking changes in v4.5.1

## Version & Release Info

- **Version:** v4.5.1
- **Release Type:** Patch
- **Target Registry:** Packagist
- **Branch:** dev → main

---

## Pre-Merge Verification

Before merging, ensure:

1. ✅ All tests pass locally: `composer test`
2. ✅ No lint/style issues: `composer lint` (if configured)
3. ✅ CHANGELOG.md updated with v4.5.1 entry
4. ✅ RELEASE_NOTES.md updated with v4.5.1 section
5. ✅ No unintended files modified (only src/Support/CaronteResponse.php and tests/Feature/MiddlewareBehaviorTest.php)
