export type WaNumberStatus = 'disconnected' | 'connecting' | 'connected' | 'logged_in';

export interface WaNumber {
  id: number;
  device_id: string;
  phone_number: string | null;
  jid: string | null;
  status: WaNumberStatus;
  created_at: string;
  updated_at: string;
  [key: string]: unknown;
}
