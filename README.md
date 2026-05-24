# README (Root of the Project)

This documentation follows the project's Coding Standards and PHPDoc Style Guide.

## Project Overview

ometra/caronte-sdk is a Laravel package that integrates a host Laravel application with a centralized Caronte authentication server.

Main capabilities:

- User authentication via Caronte (login, logout, 2FA, password recovery)
- User token validation and renewal middleware
- Management UI for users and role synchronization
- Application-to-application authentication middleware
- Protected API access token validation and scope checks
- Tenant-aware behavior for single-tenant and multi-tenant modes

Primary audience: internal development teams integrating Caronte into Laravel applications.

## Project Type & Tech Summary

- Project type: Laravel package (library), not a standalone app
- PHP version: ^8.2
- Laravel version: ^12.0
- JWT stack: lcobucci/jwt ^5.3 and lcobucci/clock ^3.2
- HTTP integration: Laravel HTTP client via package support classes
- Database: uses host app database connection; publishes package migrations for local user cache tables
- Cache: host app cache (OIDC JWKS cache uses Laravel Cache)
- Queue: no package-owned queue workers required
- External services: Caronte server HTTP API, optional OIDC issuer endpoints

## Quick Start (High-Level)

1. Install package dependencies in your host app with composer.
2. Publish package configuration and migrations.
3. Set required environment variables for CARONTE_URL, CARONTE_APP_CN, and CARONTE_APP_SECRET.
4. Run migrations in the host application.
5. Add package middleware to protected host routes.
6. Synchronize configured roles and protected API scopes.
7. Verify authentication and management routes in a local environment.

Full steps: see doc/deployment-instructions.md.

## Documentation Index

- [Deployment Instructions](doc/deployment-instructions.md)
- [API Documentation](doc/api-documentation.md)
- [Routes Documentation](doc/routes-documentation.md)
- [Artisan Commands](doc/artisan-commands.md)
- [Tests Documentation](doc/tests-documentation.md)
- [Middleware Documentation](doc/middleware.md)
- [Architecture Diagrams](doc/architecture-diagrams.md)
- [Monitoring](doc/monitoring.md)
- [Business Logic & Core Processes](doc/business-logic-and-core-processes.md)
- [Open Questions & Assumptions](doc/open-questions-and-assumptions.md)

## Standards Note

Examples and references in these docs follow the project instructions for coding conventions and PHPDoc style, using the package namespace and folder structure as the source of truth.
