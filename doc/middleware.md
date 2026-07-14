# Middleware Documentation

This directory contains one document per middleware alias registered by the package. Start here if you need the selection guide, ordering rules, or a quick path into the specific middleware behavior.

## Middleware Index

- [caronte.session](middleware/caronte.session.md)
- [caronte.roles](middleware/caronte.roles.md)
- [caronte.application](middleware/caronte.application.md)
- [caronte.protected-api-token](middleware/caronte.protected-api-token.md)
- [caronte.protected-api-scopes](middleware/caronte.protected-api-scopes.md)

## How To Read These Docs

Each middleware page follows the same structure:

1. Purpose and supported route type.
2. Step-by-step request flow.
3. Mermaid diagram of the control path.
4. Common failures.
5. Debugging tips and ordering rules.

## Shared Rules

- Always check the route definition first.
- Always confirm the required token or header exists.
- Always confirm middleware order when one middleware depends on another.
- For protected API routes, token validation must run before scope checks.
- Deprecated aliases are documented separately, but they use the same implementation as the protected API middleware.
