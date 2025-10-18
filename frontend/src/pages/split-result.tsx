import { useState, useEffect } from 'react';
import { useRouter } from 'next/router';
import { useAuth } from '@/contexts/AuthContext';
import { apiFetch } from '@/lib/api';

interface SplitCalculationResult {
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

interface Project {
  project_id: number;
  project_name: string;
}

export default function SplitResult() {
  const router = useRouter();
  const { customer } = useAuth();
  const { projectId } = router.query;
  
  const [project, setProject] = useState<Project | null>(null);
  const [splitResult, setSplitResult] = useState<SplitCalculationResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (projectId && typeof projectId === 'string') {
      fetchSplitCalculation(parseInt(projectId));
    }
  }, [projectId]);

  const fetchSplitCalculation = async (projectId: number) => {
    try {
      setLoading(true);
      setError(null);
      
      // プロジェクト情報を取得
      const projectResponse = await apiFetch<{ project: Project }>(`/api/projects/${projectId}`);
      setProject(projectResponse.project);
      
      // 割り勘計算を実行
      const splitResponse = await apiFetch<{ message: string; data: SplitCalculationResult }>(
        `/api/projects/${projectId}/split-calculation`,
        {
          method: 'POST',
        }
      );
      
      setSplitResult(splitResponse.data);
      
    } catch (err: any) {
      console.error('割り勘計算エラー:', err);
      setError(err.message || '割り勘計算に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  const handleBackToProject = () => {
    if (projectId) {
      router.push(`/projects/${projectId}`);
    }
  };

  if (!customer) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">ログインが必要です</h2>
          <p className="text-gray-600 mb-4">割り勘計算結果を表示するにはログインしてください</p>
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
          <p className="mt-4 text-gray-600">割り勘計算中...</p>
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
            onClick={handleBackToProject}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            プロジェクト詳細に戻る
          </button>
        </div>
      </div>
    );
  }

  if (!splitResult) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">計算結果が見つかりません</h2>
          <p className="text-gray-600 mb-4">割り勘計算の結果を取得できませんでした</p>
          <button
            onClick={handleBackToProject}
            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            プロジェクト詳細に戻る
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
              <h1 className="text-3xl font-bold text-gray-900">
                {project?.project_name} - 割り勘計算結果
              </h1>
              <p className="mt-2 text-gray-600">
                計算日時: {new Date(splitResult.calculation_date).toLocaleString('ja-JP')}
              </p>
            </div>
            <button
              onClick={handleBackToProject}
              className="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
            >
              ← プロジェクト詳細に戻る
            </button>
          </div>
        </div>

        {/* 合計金額 */}
        <div className="bg-white shadow rounded-lg mb-8">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900">合計金額</h2>
          </div>
          <div className="px-6 py-4">
            <div className="text-center">
              <p className="text-4xl font-bold text-gray-900">
                ¥{splitResult.total_amount.toLocaleString()}
              </p>
              <p className="text-gray-600 mt-2">プロジェクト全体の支出総額</p>
            </div>
          </div>
        </div>

        {/* メンバー別詳細 */}
        <div className="bg-white shadow rounded-lg mb-8">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900">メンバー別詳細</h2>
          </div>
          <div className="px-6 py-4">
            <div className="space-y-4">
              {splitResult.members.map((member) => (
                <div key={member.customer_id} className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="flex items-center space-x-2">
                        <h3 className="font-medium text-gray-900">{member.member_name}</h3>
                        {member.is_owner && (
                          <span className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                            オーナー
                          </span>
                        )}
                        <span className="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                          比重: {member.split_weight}
                        </span>
                      </div>
                      <div className="mt-2 grid grid-cols-2 gap-4 text-sm">
                        <div>
                          <span className="text-gray-500">実際の支払額:</span>
                          <span className="ml-2 font-medium">¥{member.total_paid.toLocaleString()}</span>
                        </div>
                        <div>
                          <span className="text-gray-500">負担額:</span>
                          <span className="ml-2 font-medium">¥{member.share_amount.toLocaleString()}</span>
                        </div>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className={`text-lg font-semibold ${
                        member.balance > 0 
                          ? 'text-green-600' 
                          : member.balance < 0 
                          ? 'text-red-600' 
                          : 'text-gray-600'
                      }`}>
                        {member.balance > 0 ? '+' : ''}¥{member.balance.toLocaleString()}
                      </p>
                      <p className="text-xs text-gray-500">
                        {member.balance > 0 ? '受取' : member.balance < 0 ? '支払' : '相殺'}
                      </p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* 支払いフロー */}
        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-medium text-gray-900">支払いフロー</h2>
            <p className="text-sm text-gray-600 mt-1">
              以下の通りに支払うことで、全員の負担が均等になります
            </p>
          </div>
          <div className="px-6 py-4">
            {splitResult.payment_flow.length === 0 ? (
              <div className="text-center text-gray-500 py-8">
                <div className="text-4xl mb-4">✅</div>
                <p>支払いの調整は必要ありません</p>
                <p className="text-sm mt-2">全員の支払額が既に適切に分散されています</p>
              </div>
            ) : (
              <div className="space-y-4">
                {splitResult.payment_flow.map((payment, index) => (
                  <div key={index} className="border border-gray-200 rounded-lg p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-4">
                        <div className="text-center">
                          <p className="font-medium text-gray-900">{payment.from_member_name}</p>
                          <p className="text-xs text-gray-500">支払人</p>
                        </div>
                        <div className="flex items-center space-x-2">
                          <div className="w-8 h-0.5 bg-gray-300"></div>
                          <span className="text-gray-400">→</span>
                          <div className="w-8 h-0.5 bg-gray-300"></div>
                        </div>
                        <div className="text-center">
                          <p className="font-medium text-gray-900">{payment.to_member_name}</p>
                          <p className="text-xs text-gray-500">受取人</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <p className="text-xl font-bold text-blue-600">
                          ¥{payment.amount.toLocaleString()}
                        </p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* 戻るボタン */}
        <div className="mt-8 text-center">
          <button
            onClick={handleBackToProject}
            className="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-md transition-colors"
          >
            ← プロジェクト詳細に戻る
          </button>
        </div>
      </div>
    </div>
  );
}
