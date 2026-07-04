export interface WorkspaceUser {
  id: number;
  user_id: number;
  workspace_id: number;
  workspace_name: string;
  email: string;
  role: string;
  type: 'user' | 'invitation';
}
