import type { Branding, Routes, TenantOption } from "../../types";

type LoginProps = {
  callback_url?: string;
  routes?: Routes;
  branding?: Branding;
  csrf_token?: string;
  tenant_options?: TenantOption[];
  pending_login?: {
    email: string;
  } | null;
};

export default function Login({
  callback_url,
  routes = {},
  branding = {},
  csrf_token,
  tenant_options = [],
  pending_login = null,
}: LoginProps) {
  const isTenantSelection = tenant_options.length > 0 && pending_login !== null;

  return (
    <section className="caronte-auth">
      <div className="caronte-auth__panel">
        <span className="caronte-kicker">
          {branding.app_name || "Caronte"}
        </span>
        <h1 className="caronte-title">
          {branding.headline || "Secure access with Caronte"}
        </h1>
        <p className="caronte-copy">
          {branding.subheadline ||
            "Authenticate users and administer access from a polished package surface."}
        </p>

        <div className="caronte-card">
          <div className="caronte-card__header">
            <h2>Sign in</h2>
            <p>Use your Caronte credentials to continue.</p>
          </div>

          <form method="POST" action={routes.login} className="caronte-form">
            <input type="hidden" name="_token" value={csrf_token} />
            {callback_url ? (
              <input type="hidden" name="callback_url" value={callback_url} />
            ) : null}

            <div>
              <label htmlFor="email" className="form-label">
                Email
              </label>
              <input
                id="email"
                type="email"
                name="email"
                className="form-control"
                defaultValue={isTenantSelection ? pending_login?.email : undefined}
                readOnly={isTenantSelection}
                required
              />
            </div>

            {!isTenantSelection ? (
              <div>
                <label htmlFor="password" className="form-label">
                  Password
                </label>
                <input
                  id="password"
                  type="password"
                  name="password"
                  className="form-control"
                  required
                />
              </div>
            ) : null}

            {tenant_options.length > 0 ? (
              <div>
                <label htmlFor="tenant_id" className="form-label">
                  Tenant
                </label>
                <select
                  id="tenant_id"
                  name="tenant_id"
                  className="form-control"
                  required
                >
                  <option value="">Select tenant</option>
                  {tenant_options.map((tenant) => (
                    <option key={tenant.tenant_id} value={tenant.tenant_id}>
                      {tenant.name}
                    </option>
                  ))}
                </select>
              </div>
            ) : null}

            <button type="submit" className="btn caronte-btn-primary">
              Continue
            </button>
          </form>

          <div className="caronte-card__footer">
            <a href={routes.passwordRecoverForm}>Forgot your password?</a>
          </div>
        </div>
      </div>
    </section>
  );
}
