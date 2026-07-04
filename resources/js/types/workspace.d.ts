import { WorkspaceUser } from '@/types/workspace-user';

export interface Workspace {
  id: number;
  name: string;
  owner?: WorkspaceUser;
  users: WorkspaceUser[];
  role: string;
  created_at: string;
  updated_at: string;
  [key: string]: unknown;
}
