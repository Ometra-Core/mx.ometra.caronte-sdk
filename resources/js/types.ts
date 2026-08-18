export type Branding = {
  app_name?: string;
  headline?: string;
  subheadline?: string;
  accent?: string;
  logo_url?: string;
  background_url?: string;
  footer_logo_url?: string;
};

export type Routes = Record<string, string | undefined>;

export type TenantOption = {
  id_tenant: string;
  name: string;
};

export type TenantSwitcherProps = {
  tenants: TenantOption[];
  currentTenantId?: string | null;
  label?: string;
  className?: string;
  disabled?: boolean;
  onTenantChange?: (tenantId: string, tenant: TenantOption) => void;
};

export type Role = {
  uri_applicationRole: string;
  app_id?: string;
  name: string;
  description?: string;
  manageable?: boolean;
};

export type UserMetadata = {
  key: string;
  value?: string;
};

export type User = {
  uri_user?: string;
  name?: string;
  email?: string;
  roles?: Role[];
  role_assignments?: Array<{ app_id: string; roles: Role[] }>;
  metadata?: UserMetadata[];
};

export type Paginated<T> = {
  data?: T[];
  links?: unknown[];
};

export type FeatureFlags = {
  metadata?: boolean;
};

export type GroupApplication = {
  app_id: string;
  name?: string;
  roles?: Role[];
};

export type GroupAccess = {
  enabled: boolean;
  error?: string | null;
  applications?: GroupApplication[];
  users?: User[];
};
