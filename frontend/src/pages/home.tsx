import Link from 'next/link';
import { useState } from 'react';

export default function Home() {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [projectName, setProjectName] = useState('');
  const [projectDescription, setProjectDescription] = useState('');
  const handleAddProject = () => {
    // 今後、ここでAPI送信などを実装
    console.log('新規プロジェクト名:', projectName);
    console.log('プロジェクトの説明:', projectDescription);
    setProjectName('');
    setProjectDescription('');
    setIsModalOpen(false);
  };

  return (
    <main className="flex flex-col items-center justify-center min-h-screen">
      <h1 className="text-3xl font-bold mb-8">ホーム画面</h1>
      <div className="flex flex-col gap-4">
        <Link href="/calculator">
          <button className="px-4 py-2 border rounded">電卓</button>
        </Link>
        <Link href="/projectslist">
          <button className="px-4 py-2 border rounded">プロジェクト一覧</button>
        </Link>
        <button
          onClick={() => setIsModalOpen(true)}
          className="px-4 py-2 border rounded transition-colors duration-300 hover:bg-gray-100"
        >
          プロジェクト追加
        </button>
      </div>

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
              <button
                onClick={() => setIsModalOpen(false)}
                className="px-4 py-2 border rounded hover:bg-gray-100"
              >
                閉じる
              </button>
              <button
                onClick={handleAddProject}
                className="px-4 py-2 border rounded bg-blue-500 text-white hover:bg-blue-600"
              >
                追加
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
