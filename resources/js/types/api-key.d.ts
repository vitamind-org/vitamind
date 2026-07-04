export interface ApiKey {
  id: number;
  name: string;
  permissions: string[];
  workspace_ids: number[];
  created_at: string;
  updated_at: string;

  [key: string]: unknown;
}
