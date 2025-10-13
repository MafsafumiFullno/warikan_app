import { useState } from 'react';
import { apiFetch } from '@/lib/api';

interface Member {
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
}

interface AddMemberModalProps {
  isOpen: boolean;
  onClose: () => void;
  projectId: number;
  onMemberAdded: (member: Member) => void;
}

export default function AddMemberModal({ 
  isOpen, 
  onClose, 
  projectId, 
  onMemberAdded 
}: AddMemberModalProps) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!name.trim()) {
      setError('メンバー名は必須です');
      return;
    }

    try {
      setLoading(true);
      setError(null);
      
      const response = await apiFetch<{ member: Member }>(`/api/projects/${projectId}/members`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name: name.trim(),
          email: email.trim() || null,
        }),
      });
      
      onMemberAdded(response.member);
      handleClose();
      
    } catch (err: any) {
      console.error('メンバー追加エラー:', err);
      setError(err.message || 'メンバーの追加に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    setName('');
    setEmail('');
    setError(null);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div className="mt-3">
          {/* ヘッダー */}
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-medium text-gray-900">メンバーを追加</h3>
            <button
              onClick={handleClose}
              className="text-gray-400 hover:text-gray-600"
              disabled={loading}
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* フォーム */}
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                メンバー名 <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                id="name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="メンバーの名前を入力"
                disabled={loading}
                required
              />
            </div>
            
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                メールアドレス <span className="text-gray-500">(任意)</span>
              </label>
              <input
                type="email"
                id="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="メールアドレスを入力（任意）"
                disabled={loading}
              />
              <p className="text-xs text-gray-500 mt-1">
                メールアドレスを入力すると、既存会員の場合はその情報を利用し、存在しない場合はゲストユーザーとして登録されます
              </p>
            </div>

            {error && (
              <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <p className="font-bold">エラー</p>
                <p>{error}</p>
              </div>
            )}

            {/* ボタン */}
            <div className="flex space-x-3 pt-4">
              <button
                type="submit"
                disabled={loading || !name.trim()}
                className="flex-1 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
              >
                {loading ? '追加中...' : 'メンバーを追加'}
              </button>
              <button
                type="button"
                onClick={handleClose}
                disabled={loading}
                className="flex-1 bg-gray-600 hover:bg-gray-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors"
              >
                キャンセル
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
