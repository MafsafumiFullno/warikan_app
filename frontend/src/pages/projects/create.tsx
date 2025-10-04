import { useState } from 'react';
import { useRouter } from 'next/router';
import { useAuth } from '@/contexts/AuthContext';
import { apiFetch } from '@/lib/api';

interface CreateProjectData {
  project_name: string;
  description: string;
  project_status: 'draft' | 'active' | 'completed' | 'archived';
}

interface ValidationErrors {
  project_name?: string;
  description?: string;
  project_status?: string;
}

export default function CreateProject() {
  const router = useRouter();
  const { customer } = useAuth();
  
  const [formData, setFormData] = useState<CreateProjectData>({
    project_name: '',
    description: '',
    project_status: 'draft',
  });
  
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const validateForm = (): boolean => {
    const errors: ValidationErrors = {};

    // プロジェクト名の検証
    if (!formData.project_name.trim()) {
      errors.project_name = 'プロジェクト名は必須です';
    } else if (formData.project_name.trim().length < 2) {
      errors.project_name = 'プロジェクト名は2文字以上で入力してください';
    } else if (formData.project_name.trim().length > 255) {
      errors.project_name = 'プロジェクト名は255文字以下で入力してください';
    }

    // 説明の検証
    if (formData.description.length > 1000) {
      errors.description = '説明は1000文字以下で入力してください';
    }

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleInputChange = (field: keyof CreateProjectData) => (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    setFormData(prev => ({
      ...prev,
      [field]: e.target.value
    }));
    
    // 入力時にその項目のエラーをクリア
    if (validationErrors[field]) {
      setValidationErrors(prev => ({
        ...prev,
        [field]: undefined
      }));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    setIsSubmitting(true);
    setError(null);
    
    try {
      console.log('プロジェクト作成リクエスト:', formData);
      
      const response = await apiFetch<{ project: any }>('/api/projects', {
        method: 'POST',
        body: JSON.stringify(formData),
      });
      
      console.log('プロジェクト作成レスポンス:', response);
      
      // 作成成功時のメッセージを表示してから遷移
      alert('プロジェクトが正常に作成されました！');
      router.push('/projectslist');
      
    } catch (err: any) {
      console.error('プロジェクト作成エラー:', err);
      setError(err.message || 'プロジェクトの作成に失敗しました');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!customer) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">ログインが必要です</h2>
          <p className="text-gray-600 mb-4">プロジェクトを作成するにはログインしてください</p>
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

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">新しいプロジェクトを作成</h1>
          <p className="mt-2 text-gray-600">割り勘プロジェクトを作成して友達と共有しましょう</p>
        </div>

        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-8">
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* プロジェクト名 */}
              <div>
                <label htmlFor="project_name" className="block text-sm font-medium text-gray-700 mb-2">
                  プロジェクト名 <span className="text-red-500">*</span>
                </label>
                <input
                  id="project_name"
                  name="project_name"
                  type="text"
                  required
                  value={formData.project_name}
                  onChange={handleInputChange('project_name')}
                  placeholder="例: 飲み会代の計算"
                  className={`w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${
                    validationErrors.project_name ? 'border-red-300' : 'border-gray-300'
                  }`}
                  maxLength={255}
                />
                {validationErrors.project_name && (
                  <p className="mt-1 text-sm text-red-600">{validationErrors.project_name}</p>
                )}
              </div>

              {/* 説明 */}
              <div>
                <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                  説明
                </label>
                <textarea
                  id="description"
                  name="description"
                  rows={4}
                  value={formData.description}
                  onChange={handleInputChange('description')}
                  placeholder="プロジェクトの詳細な説明を入力してください（例: 新年会の費用をメンバーで割り勘）"
                  className={`w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${
                    validationErrors.description ? 'border-red-300' : 'border-gray-300'
                  }`}
                  maxLength={1000}
                />
                <div className="mt-1 flex justify-between">
                  {validationErrors.description && (
                    <p className="text-sm text-red-600">{validationErrors.description}</p>
                  )}
                  <p className="text-sm text-gray-500 ml-auto">
                    {formData.description.length}/1000 文字
                  </p>
                </div>
              </div>

              {/* ステータス */}
              <div>
                <label htmlFor="project_status" className="block text-sm font-medium text-gray-700 mb-2">
                  ステータス
                </label>
                <select
                  id="project_status"
                  name="project_status"
                  value={formData.project_status}
                  onChange={handleInputChange('project_status')}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="draft">下書き</option>
                  <option value="active">進行中</option>
                  <option value="completed">完了</option>
                  <option value="archived">アーカイブ</option>
                </select>
                <p className="mt-1 text-sm text-gray-500">
                  作成時は「下書き」を推奨します
                </p>
              </div>

              {/* エラー表示 */}
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded">
                  <p className="text-sm">{error}</p>
                </div>
              )}

              {/* 送信ボタン */}
              <div className="flex items-center justify-end space-x-4">
                <button
                  type="button"
                  onClick={() => router.push('/projectslist')}
                  className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  キャンセル
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                >
                  {isSubmitting ? (
                    <>
                      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                      作成中...
                    </>
                  ) : (
                    'プロジェクトを作成'
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>

        {/* 作成例 */}
        <div className="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-medium text-blue-900 mb-3">💡 プロジェクト作成のヒント</h3>
          <ul className="space-y-2 text-sm text-blue-800">
            <li>• <strong>明確な名前</strong>: 「飲み会代」や「旅行費」など、分かりやすい名前をつけましょう</li>
            <li>• <strong>詳細な説明</strong>: 誰が参加しているか、何のための費用かを説明しましょう</li>
            <li>• <strong>ステータス管理</strong>: 下書き→進行中→完了の順で管理しましょう</li>
          </ul>
        </div>
      </div>
    </div>
  );
}
