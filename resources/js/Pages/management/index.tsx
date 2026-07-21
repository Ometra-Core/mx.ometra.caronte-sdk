import type { Branding, GroupAccess, Paginated, Role, Routes, User } from "../../types";

type ManagementIndexProps = {
  branding?: Branding;
  search?: string;
  id_tenant?: string;
  users?: Paginated<User>;
  configured_roles?: Role[];
  missing_roles?: Role[];
  outdated_roles?: Role[];
  group_access?: GroupAccess;
  routes?: Routes;
  csrf_token?: string;
};

export default function ManagementIndex({
  branding = {},
  search = "",
  id_tenant,
  users = { data: [], links: [] },
  configured_roles = [],
  missing_roles = [],
  outdated_roles = [],
  group_access = { enabled: false, applications: [], users: [] },
  routes = {},
  csrf_token,
}: ManagementIndexProps) {
  return (
    <div className="container py-4 py-lg-5">
      <div className="caronte-management-header mb-4">
        <div>
          <span className="caronte-kicker">{branding.app_name || "Caronte"}</span>
          <h1 className="caronte-title mb-2">User management</h1>
          <p className="caronte-copy mb-0">
            Tenant <strong>{id_tenant}</strong>. Roles are defined locally in
            <code> config/caronte.php </code>
            and synchronized explicitly.
          </p>
        </div>

        <form method="POST" action={routes.logout}>
          <input type="hidden" name="_token" value={csrf_token} />
          <button type="submit" className="btn caronte-btn-secondary">
            Sign out
          </button>
        </form>
      </div>

      <div className="row g-4">
        <div className="col-12 col-xl-8">
          <div className="caronte-card">
            <div className="caronte-card__header">
              <h2>Application users</h2>
              <p>
                Search, review, and navigate to the detail view for each
                tenant-scoped user.
              </p>
            </div>

            <form method="GET" action={routes.dashboard} className="row g-3 align-items-end mb-4">
              <div className="col-md-8">
                <label htmlFor="search" className="form-label">
                  Search users
                </label>
                <input
                  id="search"
                  type="text"
                  name="search"
                  defaultValue={search}
                  className="form-control"
                  placeholder="Name or email"
                />
              </div>
              <div className="col-md-4 d-grid">
                <button type="submit" className="btn caronte-btn-secondary">
                  Apply filters
                </button>
              </div>
            </form>

            <div className="table-responsive">
              <table className="table align-middle">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th className="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {(users.data || []).length === 0 ? (
                    <tr>
                      <td colSpan={3} className="text-center text-muted py-4">
                        No users matched your current filters.
                      </td>
                    </tr>
                  ) : (
                    (users.data || []).map((user) => (
                      <tr key={user.uri_user}>
                        <td>{user.name}</td>
                        <td>{user.email}</td>
                        <td className="text-end">
                          <a
                            href={(routes.usersShow || "").replace("__USER__", user.uri_user || "")}
                            className="btn btn-sm caronte-btn-secondary"
                          >
                            Manage
                          </a>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="col-12 col-xl-4">
          <div className="caronte-card mb-4">
            <div className="caronte-card__header">
              <h2>Sync configured roles</h2>
              <p>
                Remote role definitions should match local package
                configuration.
              </p>
            </div>

            <div className="caronte-status-list">
              <div>
                <span className="caronte-status-list__label">Configured roles</span>
                <strong>{configured_roles.length}</strong>
              </div>
              <div>
                <span className="caronte-status-list__label">Missing remotely</span>
                <strong>{missing_roles.length}</strong>
              </div>
              <div>
                <span className="caronte-status-list__label">
                  Descriptions outdated
                </span>
                <strong>{outdated_roles.length}</strong>
              </div>
            </div>

            <form method="POST" action={routes.rolesSync} className="mt-4">
              <input type="hidden" name="_token" value={csrf_token} />
              <button type="submit" className="btn caronte-btn-primary w-100">
                Synchronize roles now
              </button>
            </form>
          </div>

          <div className="caronte-card">
            <div className="caronte-card__header">
              <h2>Create user</h2>
              <p>
                New users are created in Caronte and immediately scoped to the
                configured role set you choose.
              </p>
            </div>

            <form method="POST" action={routes.usersStore} className="caronte-form">
              <input type="hidden" name="_token" value={csrf_token} />

              <div>
                <label htmlFor="name" className="form-label">
                  Name
                </label>
                <input id="name" type="text" name="name" className="form-control" required />
              </div>

              <div>
                <label htmlFor="email" className="form-label">
                  Email
                </label>
                <input id="email" type="email" name="email" className="form-control" required />
              </div>

              <div>
                <label htmlFor="password" className="form-label">
                  Temporary password
                </label>
                <input id="password" type="password" name="password" className="form-control" required />
              </div>

              <div>
                <label htmlFor="password_confirmation" className="form-label">
                  Confirm password
                </label>
                <input
                  id="password_confirmation"
                  type="password"
                  name="password_confirmation"
                  className="form-control"
                  required
                />
              </div>

              <div>
                <label className="form-label">Configured roles</label>
                <div className="caronte-checkbox-list">
                  {configured_roles.map((role) => (
                    <label className="caronte-checkbox" key={role.uri_applicationRole}>
                      <input type="checkbox" name="roles[]" value={role.uri_applicationRole} />
                      <span>
                        <strong>{role.name}</strong>
                        <small>{role.description}</small>
                      </span>
                    </label>
                  ))}
                </div>
              </div>

              <button type="submit" className="btn caronte-btn-primary">
                Create user
              </button>
            </form>
          </div>
        </div>
      </div>

      <div className="caronte-card mt-4">
        <div className="caronte-card__header">
          <h2>Group access</h2>
          <p>Manage non-root roles for applications in the configured application group.</p>
        </div>

        {!group_access.enabled ? (
          <div className="alert alert-warning mb-0">
            {group_access.error || "Group access is not available for this application."}
          </div>
        ) : (group_access.applications || []).length === 0 || (group_access.users || []).length === 0 ? (
          <div className="text-muted">No group applications or users matched the current filters.</div>
        ) : (
          <div className="row g-3">
            {(group_access.users || []).map((user) => (
              <div className="col-12" key={user.uri_user}>
                <div className="border rounded p-3">
                  <div className="fw-semibold mb-3">
                    {user.name} · {user.email}
                  </div>
                  <div className="row g-3">
                    {(group_access.applications || []).map((application) => {
                      const manageableRoles = (application.roles || []).filter(
                        (role) => role.manageable !== false,
                      );
                      const assignedRoleUris = (user.role_assignments || [])
                        .filter((assignment) => assignment.app_id === application.app_id)
                        .flatMap((assignment) => assignment.roles || [])
                        .map((role) => role.uri_applicationRole);
                      const action = (routes.groupRolesSync || "")
                        .replace("__USER__", user.uri_user || "")
                        .replace("__APP__", application.app_id);

                      return (
                        <form method="POST" action={action} className="col-12 col-lg-6" key={application.app_id}>
                          <input type="hidden" name="_token" value={csrf_token} />
                          <input type="hidden" name="_method" value="PUT" />
                          <div className="border rounded p-3 h-100">
                            <div className="fw-semibold mb-2">{application.name || application.app_id}</div>
                            {manageableRoles.length === 0 ? (
                              <div className="text-muted small mb-3">Only reserved roles are defined for this app.</div>
                            ) : (
                              <div className="caronte-checkbox-list">
                                {manageableRoles.map((role) => (
                                  <label className="caronte-checkbox" key={role.uri_applicationRole}>
                                    <input
                                      type="checkbox"
                                      name="roles[]"
                                      value={role.uri_applicationRole}
                                      defaultChecked={assignedRoleUris.includes(role.uri_applicationRole)}
                                    />
                                    <span>
                                      <strong>{role.name}</strong>
                                      <small>{role.description}</small>
                                    </span>
                                  </label>
                                ))}
                              </div>
                            )}
                            <button type="submit" className="btn caronte-btn-secondary btn-sm mt-2">
                              Save group roles
                            </button>
                          </div>
                        </form>
                      );
                    })}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
