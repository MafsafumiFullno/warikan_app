import { useState } from 'react';
import AuthGuard from '@/components/AuthGuard';
import Layout from '@/components/Layout';
import { useAuth } from '@/contexts/AuthContext';
import MainContent from '@/components/MainContent';
import { apiFetch } from '@/lib/api';

export default function Home() {
  const [activeTab, setActiveTab] = useState('projects'); // デフォルトはプロジェクト一覧
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [projectName, setProjectName] = useState('');
  const [projectDescription, setProjectDescription] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { customer, logout } = useAuth();

  const handleAddProject = async () => {
    // バリデーション
    if (!projectName.trim()) {
      setError('プロジェクト名は必須です');
      return;
    }
    
    if (!projectDescription.trim()) {
      setError('プロジェクトの説明は必須です');
      return;
    }

    setIsSubmitting(true);
    setError(null);
    
    try {
      console.log('プロジェクト作成リクエスト:', {
        project_name: projectName.trim(),
        description: projectDescription.trim(),
        project_status: 'draft'
      });
      
      const response = await apiFetch<{ project: any }>('/api/projects', {
        method: 'POST',
        body: JSON.stringify({
          project_name: projectName.trim(),
          description: projectDescription.trim(),
          project_status: 'draft'
        }),
      });
      
      console.log('プロジェクト作成レスポンス:', response);
      
      // 成功時の処理
      setProjectName('');
      setProjectDescription('');
      setIsModalOpen(false);
      
      // 成功メッセージを表示
      alert('プロジェクトが正常に作成されました！');
      
      // ページをリロードしてプロジェクト一覧を更新
      window.location.reload();
      
    } catch (err: any) {
      console.error('プロジェクト作成エラー:', err);
      setError(err.message || 'プロジェクトの作成に失敗しました');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthGuard>
      <Layout title="プロジェクト一覧" showProjectsButton={true}>
        <div>
          {/* プロジェクト追加ボタンをヘッダーに移動 */}
          <div className="flex justify-end mb-6">
            <button 
              onClick={() => setIsModalOpen(true)} 
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              新しいプロジェクトを追加
            </button>
          </div>
          
          {/* メインコンテンツ */}
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <MainContent activeTab={activeTab} />
          </div>
        </div>

        {/* プロジェクト追加モーダル */}
        {isModalOpen && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white p-6 rounded-lg w-80 shadow-md">
              <h2 className="text-xl mb-4">プロジェクト追加</h2>
              
              {/* エラーメッセージ */}
              {error && (
                <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                  {error}
                </div>
              )}
              
              <input
                type="text"
                value={projectName}
                onChange={(e) => {
                  setProjectName(e.target.value);
                  if (error) setError(null); // エラーをクリア
                }}
                placeholder="プロジェクト名"
                className="w-full px-3 py-2 mb-4 border rounded"
                required
                disabled={isSubmitting}
              />
              <textarea
                value={projectDescription}
                onChange={(e) => {
                  setProjectDescription(e.target.value);
                  if (error) setError(null); // エラーをクリア
                }}
                placeholder="プロジェクトの説明"
                className="w-full px-3 py-2 mb-4 border rounded resize-y min-h-[100px]"
                required
                disabled={isSubmitting}
              />
              <div className="flex justify-end gap-2">
                <button 
                  onClick={() => {
                    setIsModalOpen(false);
                    setError(null);
                    setProjectName('');
                    setProjectDescription('');
                  }} 
                  className="px-4 py-2 border rounded hover:bg-gray-100"
                  disabled={isSubmitting}
                >
                  閉じる
                </button>
                <button 
                  onClick={handleAddProject} 
                  className="px-4 py-2 border rounded bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                  disabled={isSubmitting}
                >
                  {isSubmitting ? '作成中...' : '追加'}
                </button>
              </div>
            </div>
          </div>
        )}
      </Layout>
    </AuthGuard>
  );
}
