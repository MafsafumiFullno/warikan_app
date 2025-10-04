import { useState } from 'react';
import AuthGuard from '@/components/AuthGuard';
import Layout from '@/components/Layout';
import { useAuth } from '@/contexts/AuthContext';
import MainContent from '@/components/MainContent';

export default function Home() {
  const [activeTab, setActiveTab] = useState('projects'); // デフォルトはプロジェクト一覧
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [projectName, setProjectName] = useState('');
  const [projectDescription, setProjectDescription] = useState('');
  const { customer, logout } = useAuth();

  const handleAddProject = () => {
    console.log('新規プロジェクト名:', projectName);
    console.log('プロジェクトの説明:', projectDescription);
    setProjectName('');
    setProjectDescription('');
    setIsModalOpen(false);
  };

  return (
    <AuthGuard>
      <Layout title="ホーム画面" showProjectsButton={true}>
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
              <input
                type="text"
                value={projectName}
                onChange={(e) => setProjectName(e.target.value)}
                placeholder="プロジェクト名"
                className="w-full px-3 py-2 mb-4 border rounded"
                required
              />
              <textarea
                value={projectDescription}
                onChange={(e) => setProjectDescription(e.target.value)}
                placeholder="プロジェクトの説明"
                className="w-full px-3 py-2 mb-4 border rounded resize-y min-h-[100px]"
                required
              />
              <div className="flex justify-end gap-2">
                <button onClick={() => setIsModalOpen(false)} className="px-4 py-2 border rounded hover:bg-gray-100">閉じる</button>
                <button onClick={handleAddProject} className="px-4 py-2 border rounded bg-blue-500 text-white hover:bg-blue-600">追加</button>
              </div>
            </div>
          </div>
        )}
      </Layout>
    </AuthGuard>
  );
}
