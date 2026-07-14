export type Branding = {
  app_name?: string;
  headline?: string;
  subheadline?: string;
  accent?: string;
};

export type Routes = Record<string, string | undefined>;

export type TenantOption = {
  tenant_id: string;
  name: string;
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
  metadata?: UserMetadata[];
};

export type Paginated<T> = {
  data?: T[];
  links?: unknown[];
};

export type FeatureFlags = {
  metadata?: boolean;
};

export type SuiteApplication = {
  app_id: string;
  name?: string;
  roles?: Role[];
};

export type SuiteAccess = {
  enabled: boolean;
  error?: string | null;
  applications?: SuiteApplication[];
  users?: User[];
};
