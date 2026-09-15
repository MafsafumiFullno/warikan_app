import type { PaginationInfo } from './api';

export type ProjectStatus = 'draft' | 'active' | 'completed' | 'archived' | string;

export interface Project {
  project_id: number;
  project_name: string;
  description?: string;
  project_status: ProjectStatus;
  created_at: string;
  updated_at: string;
}

export interface ProjectsResponse {
  projects: Project[];
  pagination: PaginationInfo;
}

export interface ProjectResponse {
  project: Project;
}

export interface ProjectAccess {
  isOwner: boolean;
  isMember: boolean;
}

export interface ProjectAccessResponse extends ProjectResponse, ProjectAccess {}

export interface Member {
  id: number;
  project_member_id: number;
  customer_id: number;
  role: string;
  role_name: string;
  split_weight: number;
  memo?: string;
  name: string;
  email?: string;
  is_guest: boolean;
  joined_at: string;
  total_expense: number;
}

export interface Accounting {
  task_id: number;
  project_id: number;
  project_task_code: number;
  task_name: string;
  task_member_name: string;
  member_id?: number | null;
  customer_id?: number;
  accounting_amount: number;
  accounting_type: string;
  breakdown?: string;
  payment_id?: string;
  memo?: string;
  target_members?: string[];
  target_member_ids?: number[];
  del_flg: boolean;
  created_at: string;
  updated_at: string;
}

export interface SplitCalculationResult {
  project_id: number;
  total_amount: number;
  members: Array<{
    customer_id: number;
    member_name: string;
    split_weight: number;
    is_owner: boolean;
    total_paid: number;
    share_amount: number;
    balance: number;
  }>;
  payment_flow: Array<{
    from_customer_id: number;
    from_member_name: string;
    to_customer_id: number;
    to_member_name: string;
    amount: number;
  }>;
  calculation_date: string;
}
