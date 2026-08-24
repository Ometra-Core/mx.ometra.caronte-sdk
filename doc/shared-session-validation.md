# Shared Session Configuration Validation

Caronte SDK installs the generic `validate-group-session-config` Composer binary. It validates a group of application
ENV files without knowing application names, repository layouts, or expected values in advance.

```bash
vendor/bin/validate-group-session-config --config group-session-config.json
```

The configuration directory is the default workspace. Use `--workspace` when application paths resolve elsewhere:

```bash
vendor/bin/validate-group-session-config \
    --config /config/group-session-config.json \
    --workspace /srv/applications
```

The version 1 JSON contract contains a group name, applications with ordered ENV files, and validation rules:

```json
{
  "version": 1,
  "group": "shared-session",
  "applications": [
    {
      "name": "application-one",
      "path": "application-one",
      "env_files": [".env.d/session.env", ".env.d/auth.env"]
    }
  ],
  "rules": [
    {"type": "equals", "key": "SESSION_DRIVER", "value": "redis", "required": true},
    {"type": "same", "key": "APP_KEY", "required": false, "sensitive": true}
  ]
}
```

ENV files are merged in declaration order and later values take precedence. `equals` checks an expected value; `same`
requires equality across target applications. An optional `applications` array limits a rule to named applications.
Sensitive rules compare SHA-256 hashes and never include values in command output.

Application and ENV paths must be relative and remain inside the workspace. The command exits with `0` for a valid
group, `1` for validation violations, and `2` for usage, JSON, schema, or path errors.
