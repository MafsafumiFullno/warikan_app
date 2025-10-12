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

export default function ProjectDetail() {
  const router = useRouter();
  const { customer } = useAuth();
  const { id } = router.query;
  
  const [project, setProject] = useState<Project | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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
      setProject(response.project);
      
    } catch (err: any) {
      console.error('プロジェクト詳細取得エラー:', err);
      setError(err.message || 'プロジェクトの取得に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  const getStatusLabel = (status: string) => {
    const statusMap: { [key: string]: string } = {
      'draft': '下書き',
      'active': '進行中',
      'completed': '完了',
      'archived': 'アーカイブ'
    };
    return statusMap[status] || status;
  };

  const getStatusColor = (status: string) => {
    const colorMap: { [key: string]: string } = {
      'draft': 'bg-gray-100 text-gray-800',
      'active': 'bg-blue-100 text-blue-800',
      'completed': 'bg-green-100 text-green-800',
      'archived': 'bg-yellow-100 text-yellow-800'
    };
    return colorMap[status] || 'bg-gray-100 text-gray-800';
  };

  if (!customer) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">ログインが必要です</h2>
          <p className="text-gray-600 mb-4">プロジェクトを表示するにはログインしてください</p>
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

  if (error) {
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
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* ヘッダー */}
        <div className="mb-8">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">{project.project_name}</h1>
              <p className="mt-2 text-gray-600">
                作成日: {new Date(project.created_at).toLocaleDateString('ja-JP')}
              </p>
            </div>
            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(project.project_status)}`}>
              {getStatusLabel(project.project_status)}
            </span>
          </div>
        </div>

        {/* メインコンテンツ */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* プロジェクト情報 */}
          <div className="lg:col-span-2 space-y-6">
            <div className="bg-white shadow rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-gray-900">プロジェクト詳細</h2>
              </div>
              <div className="px-6 py-4">
                {project.description ? (
                  <div className="prose max-w-none">
                    <p className="text-gray-700 whitespace-pre-wrap">{project.description}</p>
                  </div>
                ) : (
                  <p className="text-gray-500 italic">説明がありません</p>
                )}
              </div>
            </div>

            {/* メンバー管理（今後実装予定） */}
            <div className="bg-white shadow rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-gray-900">メンバー</h2>
              </div>
              <div className="px-6 py-4">
                <div className="text-center text-gray-500 py-8">
                  <div className="text-4xl mb-4">👥</div>
                  <p>メンバー機能は近日実装予定です</p>
                </div>
              </div>
            </div>
          </div>

          {/* サイドバー */}
          <div className="space-y-6">
            {/* アクションボタン */}
            <div className="bg-white shadow rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-medium text-gray-900">アクション</h3>
              </div>
              <div className="px-6 py-4 space-y-3">
                <button
                  onClick={() => router.push(`/calculator?project=${project.project_id}`)}
                  className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
                >
                  割り勘計算を開始
                </button>
                <button
                  onClick={() => router.push(`/projects/${project.project_id}/edit`)}
                  className="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
                >
                  プロジェクトを編集
                </button>
              </div>
            </div>

            {/* プロジェクト情報 */}
            <div className="bg-white shadow rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-medium text-gray-900">プロジェクト情報</h3>
              </div>
              <div className="px-6 py-4 space-y-3">
                <div>
                  <dt className="text-sm font-medium text-gray-500">プロジェクトID</dt>
                  <dd className="mt-1 text-sm text-gray-900">{project.project_id}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">ステータス</dt>
                  <dd className="mt-1">
                    <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(project.project_status)}`}>
                      {getStatusLabel(project.project_status)}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">作成日時</dt>
                  <dd className="mt-1 text-sm text-gray-900">
                    {new Date(project.created_at).toLocaleString('ja-JP')}
                  </dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">最終更新</dt>
                  <dd className="mt-1 text-sm text-gray-900">
                    {new Date(project.updated_at).toLocaleString('ja-JP')}
                  </dd>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* 戻るボタン */}
        <div className="mt-8">
          <button
            onClick={() => router.push('/projectslist')}
            className="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
          >
            ← プロジェクト一覧に戻る
          </button>
        </div>
      </div>
    </div>
  );
}
