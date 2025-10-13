import { useState, useEffect } from 'react';
import { useRouter } from 'next/router';
import { useAuth } from '@/contexts/AuthContext';
import { apiFetch } from '@/lib/api';

interface Project {
  project_id: number;
  project_name: string;
  description?: string;
  project_status: string;
  created_at: string;
  updated_at: string;
}

const PROJECT_STATUSES = [
  { value: 'draft', label: '下書き' },
  { value: 'active', label: '進行中' },
  { value: 'completed', label: '完了' },
  { value: 'archived', label: 'アーカイブ' }
];

export default function ProjectEdit() {
  const router = useRouter();
  const { customer } = useAuth();
  const { id } = router.query;
  
  const [project, setProject] = useState<Project | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // フォームの状態
  const [formData, setFormData] = useState({
    project_name: '',
    description: '',
    project_status: 'draft'
  });

  useEffect(() => {
    if (id && typeof id === 'string') {
      fetchProject(parseInt(id));
    }
  }, [id]);

  const fetchProject = async (projectId: number) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await apiFetch<{ project: Project }>(`/api/projects/${projectId}`);
      const projectData = response.project;
      setProject(projectData);
      
      // フォームデータを初期化
      setFormData({
        project_name: projectData.project_name,
        description: projectData.description || '',
        project_status: projectData.project_status
      });
      
    } catch (err: any) {
      console.error('プロジェクト詳細取得エラー:', err);
      setError(err.message || 'プロジェクトの取得に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!project) return;
    
    try {
      setSaving(true);
      setError(null);
      
      await apiFetch(`/api/projects/${project.project_id}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
      });
      
      // 成功したらプロジェクト詳細ページに戻る
      router.push(`/projects/${project.project_id}`);
      
    } catch (err: any) {
      console.error('プロジェクト更新エラー:', err);
      setError(err.message || 'プロジェクトの更新に失敗しました');
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = () => {
    router.push(`/projects/${id}`);
  };

  if (!customer) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">ログインが必要です</h2>
          <p className="text-gray-600 mb-4">プロジェクトを編集するにはログインしてください</p>
          <button
            onClick={() => router.push('/login')}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            ログインページへ
          </button>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">プロジェクトを読み込み中...</p>
        </div>
      </div>
    );
  }

  if (error && !project) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <p className="font-bold">エラーが発生しました</p>
            <p>{error}</p>
          </div>
          <button
            onClick={() => router.push('/projectslist')}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            プロジェクト一覧に戻る
          </button>
        </div>
      </div>
    );
  }

  if (!project) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">プロジェクトが見つかりません</h2>
          <p className="text-gray-600 mb-4">指定されたプロジェクトは存在しないか、アクセス権限がありません</p>
          <button
            onClick={() => router.push('/projectslist')}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            プロジェクト一覧に戻る
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* ヘッダー */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">プロジェクトを編集</h1>
          <p className="mt-2 text-gray-600">
            プロジェクトの詳細情報を編集できます
          </p>
        </div>

        {/* エラーメッセージ */}
        {error && (
          <div className="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <p className="font-bold">エラーが発生しました</p>
            <p>{error}</p>
          </div>
        )}

        {/* 編集フォーム */}
        <div className="bg-white shadow rounded-lg">
          <form onSubmit={handleSubmit} className="p-6 space-y-6">
            {/* プロジェクト名 */}
            <div>
              <label htmlFor="project_name" className="block text-sm font-medium text-gray-700 mb-2">
                プロジェクト名 <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                id="project_name"
                name="project_name"
                value={formData.project_name}
                onChange={handleInputChange}
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                placeholder="プロジェクト名を入力してください"
              />
            </div>

            {/* プロジェクト詳細 */}
            <div>
              <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                プロジェクト詳細
              </label>
              <textarea
                id="description"
                name="description"
                value={formData.description}
                onChange={handleInputChange}
                rows={6}
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                placeholder="プロジェクトの詳細説明を入力してください"
              />
            </div>

            {/* プロジェクトステータス */}
            <div>
              <label htmlFor="project_status" className="block text-sm font-medium text-gray-700 mb-2">
                プロジェクトステータス <span className="text-red-500">*</span>
              </label>
              <select
                id="project_status"
                name="project_status"
                value={formData.project_status}
                onChange={handleInputChange}
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
              >
                {PROJECT_STATUSES.map((status) => (
                  <option key={status.value} value={status.value}>
                    {status.label}
                  </option>
                ))}
              </select>
            </div>

            {/* ボタン */}
            <div className="flex justify-end space-x-4 pt-6 border-t border-gray-200">
              <button
                type="button"
                onClick={handleCancel}
                disabled={saving}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                キャンセル
              </button>
              <button
                type="submit"
                disabled={saving}
                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                {saving ? '保存中...' : '保存'}
              </button>
            </div>
          </form>
        </div>

        {/* 戻るボタン */}
        <div className="mt-8">
          <button
            onClick={() => router.push(`/projects/${id}`)}
            className="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
          >
            ← プロジェクト詳細に戻る
          </button>
        </div>
      </div>
    </div>
  );
}
