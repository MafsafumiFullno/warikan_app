import { useEffect, useState } from 'react';
import { useRouter } from 'next/router';
import { apiFetch } from '@/lib/api';

interface SharedMember {
  project_member_id: number;
  name: string;
  split_weight: number;
  total_expense: number;
}

interface SharedAccounting {
  task_name: string;
  accounting_amount: number;
  accounting_type: string;
  target_members: string[];
  payer_name: string;
}

interface SharedProject {
  project_id: number;
  project_name: string;
  description?: string;
  created_at: string;
  updated_at: string;
  members: SharedMember[];
  accountings: SharedAccounting[];
}

interface SharedProjectResponse {
  project: SharedProject;
  capabilities: {
    can_edit: boolean;
  };
}

export default function SharedProjectDetail() {
  const router = useRouter();
  const { token } = router.query;
  const [project, setProject] = useState<SharedProject | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token || typeof token !== 'string') {
      return;
    }

    const fetchSharedProject = async () => {
      try {
        setLoading(true);
        setError(null);

        const response = await apiFetch<SharedProjectResponse>(`/api/share/${token}`);
        setProject(response.project);
      } catch (err: any) {
        setError(err.message || '共有プロジェクトの取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchSharedProject();
  }, [token]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <p className="text-gray-600">共有プロジェクトを読み込み中...</p>
      </div>
    );
  }

  if (error || !project) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow p-6 text-center">
          <p className="text-red-600 font-medium mb-2">共有リンクを表示できません</p>
          <p className="text-sm text-gray-600">{error || '共有リンクが無効です'}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center justify-between">
            <h1 className="text-2xl font-bold text-gray-900">{project.project_name}</h1>
            <span className="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">閲覧専用</span>
          </div>
          <p className="text-sm text-gray-500 mt-1">
            最終更新: {new Date(project.updated_at).toLocaleString('ja-JP')}
          </p>
          <div className="mt-4">
            {project.description ? (
              <p className="text-gray-700 whitespace-pre-wrap">{project.description}</p>
            ) : (
              <p className="text-gray-500 italic">説明がありません</p>
            )}
          </div>
        </div>

        <div className="bg-white shadow rounded-lg p-6">
          <h2 className="text-lg font-medium text-gray-900 mb-3">メンバー</h2>
          <ul className="space-y-3">
            {project.members.map((member) => (
              <li key={member.project_member_id} className="text-sm text-gray-700 border border-gray-100 rounded p-3">
                <p className="font-medium text-gray-900">{member.name}</p>
                <p>割り勘比重: {member.split_weight}</p>
                <p>支出合計: ¥{member.total_expense.toLocaleString()}</p>
              </li>
            ))}
          </ul>
        </div>

        <div className="bg-white shadow rounded-lg p-6">
          <h2 className="text-lg font-medium text-gray-900 mb-3">会計一覧</h2>
          {project.accountings.length === 0 ? (
            <p className="text-sm text-gray-500">会計情報はありません</p>
          ) : (
            <ul className="space-y-3">
              {project.accountings.map((accounting, index) => (
                <li key={`${accounting.task_name}-${index}`} className="text-sm text-gray-700 border border-gray-100 rounded p-3">
                  <p className="font-medium text-gray-900">{accounting.task_name}</p>
                  <p>金額: ¥{accounting.accounting_amount.toLocaleString()}</p>
                  <p>区分: {accounting.accounting_type === 'expense' ? '支出' : accounting.accounting_type === 'income' ? '収入' : accounting.accounting_type}</p>
                  <p>対象メンバー: {accounting.target_members.length > 0 ? accounting.target_members.join('、') : 'なし'}</p>
                  <p>支払い者: {accounting.payer_name}</p>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}
