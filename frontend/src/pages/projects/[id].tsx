import { useState, useEffect } from 'react';
import { useRouter } from 'next/router';
import { useAuth } from '@/contexts/AuthContext';
import { apiFetch } from '@/lib/api';
import AccountingModal from '@/components/AccountingModal';
import EditAccountingModal from '@/components/EditAccountingModal';
import MembersList from '@/components/MembersList';

interface Project {
  project_id: number;
  project_name: string;
  description?: string;
  project_status: string;
  created_at: string;
  updated_at: string;
}

interface Accounting {
  task_id: number;
  project_id: number;
  project_task_code: number;
  task_name: string;
  task_member_name: string;
  customer_id: number;
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
  total_expense: number;
}

interface ShareLink {
  project_id: number;
  token: string;
  share_url: string;
  permission: string;
  created_at: string;
}

export default function ProjectDetail() {
  const router = useRouter();
  const { customer } = useAuth();
  const { id } = router.query;
  
  const [project, setProject] = useState<Project | null>(null);
  const [accountings, setAccountings] = useState<Accounting[]>([]);
  const [members, setMembers] = useState<Member[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAccountingModal, setShowAccountingModal] = useState(false);
  const [showEditAccountingModal, setShowEditAccountingModal] = useState(false);
  const [selectedAccounting, setSelectedAccounting] = useState<Accounting | null>(null);
  const [shareLink, setShareLink] = useState<ShareLink | null>(null);
  const [shareLinkLoading, setShareLinkLoading] = useState(false);
  const [shareLinkMessage, setShareLinkMessage] = useState<string | null>(null);

  useEffect(() => {
    if (id && typeof id === 'string') {
      fetchProject(parseInt(id));
      fetchAccountings(parseInt(id));
      fetchMembers(parseInt(id));
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

  const fetchAccountings = async (projectId: number) => {
    try {
      const response = await apiFetch<{ accountings: Accounting[] }>(`/api/projects/${projectId}/accountings`);
      setAccountings(response.accountings);
    } catch (err: any) {
      console.error('会計一覧取得エラー:', err);
      // エラーを表示する（デバッグ用）
      setError(`会計一覧の取得に失敗しました: ${err.message}`);
    }
  };

  const fetchMembers = async (projectId: number) => {
    try {
      const response = await apiFetch<{ members: Member[] }>(`/api/projects/${projectId}/members`);
      setMembers(response.members);
    } catch (err: any) {
      console.error('メンバー一覧取得エラー:', err);
      // エラーは表示しない（メンバーがない場合もあるため）
    }
  };

  const handleAccountingAdded = (newAccounting: Accounting) => {
    setAccountings(prev => [newAccounting, ...prev]);
  };

  const handleAccountingUpdated = (updatedAccounting: Accounting) => {
    setAccountings(prev => prev.map(accounting => 
      accounting.task_id === updatedAccounting.task_id ? updatedAccounting : accounting
    ));
  };

  const handleAccountingClick = (accounting: Accounting) => {
    setSelectedAccounting(accounting);
    setShowEditAccountingModal(true);
  };

  const handleMemberAdded = (newMember: Member) => {
    setMembers(prev => [...prev, newMember]);
  };

  const handleMemberRemoved = (memberId: number) => {
    setMembers(prev => prev.filter(member => member.project_member_id !== memberId));
  };

  const createShareLink = async () => {
    if (!project) return;

    try {
      setShareLinkLoading(true);
      setShareLinkMessage(null);

      const response = await apiFetch<{ share_link: ShareLink }>(`/api/projects/${project.project_id}/share-link`, {
        method: 'POST',
      });

      setShareLink(response.share_link);
      setShareLinkMessage('共有リンクを作成しました');
    } catch (err: any) {
      setShareLinkMessage(err.message || '共有リンクの作成に失敗しました');
    } finally {
      setShareLinkLoading(false);
    }
  };

  const copyShareLink = async () => {
    if (!shareLink?.share_url) return;

    try {
      await navigator.clipboard.writeText(shareLink.share_url);
      setShareLinkMessage('共有リンクをコピーしました');
    } catch (err: any) {
      setShareLinkMessage(err.message || '共有リンクのコピーに失敗しました');
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

            {/* メンバー管理 */}
            {project && (
              <MembersList
                projectId={project.project_id}
                currentUserId={customer?.customer_id || 0}
                isOwner={members.some(member => member.customer_id === customer?.customer_id && member.role === 'owner')}
                onMemberAdded={handleMemberAdded}
                onMemberRemoved={handleMemberRemoved}
              />
            )}

            {/* 会計一覧 */}
            <div className="bg-white shadow rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-gray-900">会計一覧</h2>
              </div>
              <div className="px-6 py-4">
                {accountings.length === 0 ? (
                  <div className="text-center text-gray-500 py-8">
                    <div className="text-4xl mb-4">💰</div>
                    <p>会計がまだ追加されていません</p>
                    <p className="text-sm mt-2">「会計を追加」ボタンから会計を追加してください</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {accountings.map((accounting) => (
                      <div 
                        key={accounting.task_id} 
                        className="border border-gray-200 rounded-lg p-4 cursor-pointer hover:bg-gray-50 transition-colors"
                        onClick={() => handleAccountingClick(accounting)}
                      >
                        <div className="flex items-center justify-between">
                          <div className="flex-1">
                            <div className="flex items-center space-x-2">
                              <h3 className="font-medium text-gray-900">{accounting.task_name}</h3>
                              <span className={`px-2 py-1 text-xs rounded-full ${
                                accounting.accounting_type === 'expense' 
                                  ? 'bg-red-100 text-red-800' 
                                  : accounting.accounting_type === 'income'
                                  ? 'bg-green-100 text-green-800'
                                  : 'bg-blue-100 text-blue-800'
                              }`}>
                                {accounting.accounting_type === 'expense' ? '支出' : 
                                 accounting.accounting_type === 'income' ? '収入' : '振替'}
                              </span>
                            </div>
                            {accounting.breakdown && (
                              <p className="text-sm text-gray-600 mt-1">{accounting.breakdown}</p>
                            )}
                            {accounting.target_members && accounting.target_members.length > 0 && (
                              <div className="mt-2">
                                <span className="text-xs text-gray-500">対象メンバー:</span>
                                <div className="flex flex-wrap gap-1 mt-1">
                                  {accounting.target_members.map((memberName, index) => (
                                    <span key={index} className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                      {memberName}
                                    </span>
                                  ))}
                                </div>
                              </div>
                            )}
                            <p className="text-xs text-gray-500 mt-2">
                              {accounting.task_member_name} • {new Date(accounting.created_at).toLocaleDateString('ja-JP')}
                            </p>
                          </div>
                          <div className="text-right">
                            <p className={`text-lg font-semibold ${
                              accounting.accounting_type === 'expense' 
                                ? 'text-red-600' 
                                : accounting.accounting_type === 'income'
                                ? 'text-green-600'
                                : 'text-blue-600'
                            }`}>
                              {accounting.accounting_type === 'expense' ? '-' : '+'}¥{accounting.accounting_amount.toLocaleString()}
                            </p>
                          </div>
                        </div>
                      </div>
                    ))}
                    <div className="border-t border-gray-200 pt-4 mt-4">
                      <div className="flex justify-between items-center">
                        <span className="font-medium text-gray-900">合計</span>
                        <span className="text-xl font-bold text-gray-900">
                          ¥{accountings.reduce((sum, accounting) => {
                            return sum + (accounting.accounting_type === 'expense' ? -accounting.accounting_amount : accounting.accounting_amount);
                          }, 0).toLocaleString()}
                        </span>
                      </div>
                    </div>
                  </div>
                )}
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
                  onClick={() => setShowAccountingModal(true)}
                  className="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors"
                >
                  会計を追加
                </button>
                <button
                  onClick={() => router.push(`/split-result?projectId=${project.project_id}`)}
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
                {members.some(member => member.customer_id === customer?.customer_id && member.role === 'owner') && (
                  <>
                    <button
                      onClick={createShareLink}
                      disabled={shareLinkLoading}
                      className="w-full bg-purple-600 hover:bg-purple-700 disabled:bg-purple-300 text-white font-medium py-2 px-4 rounded-md transition-colors"
                    >
                      {shareLinkLoading ? '共有リンク作成中...' : '共有リンクを作成'}
                    </button>
                    {shareLink && (
                      <div className="space-y-2">
                        <p className="text-xs text-gray-600 break-all">{shareLink.share_url}</p>
                        <button
                          onClick={copyShareLink}
                          className="w-full bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-2 px-4 rounded-md transition-colors"
                        >
                          共有リンクをコピー
                        </button>
                      </div>
                    )}
                    {shareLinkMessage && (
                      <p className="text-xs text-gray-600">{shareLinkMessage}</p>
                    )}
                  </>
                )}
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

      {/* 会計追加モーダル */}
      {project && (
        <AccountingModal
          isOpen={showAccountingModal}
          onClose={() => setShowAccountingModal(false)}
          projectId={project.project_id}
          members={members}
          onAccountingAdded={handleAccountingAdded}
        />
      )}

      {/* 会計編集モーダル */}
      {project && selectedAccounting && (
        <EditAccountingModal
          isOpen={showEditAccountingModal}
          onClose={() => {
            setShowEditAccountingModal(false);
            setSelectedAccounting(null);
          }}
          projectId={project.project_id}
          taskId={selectedAccounting.task_id}
          members={members}
          accounting={selectedAccounting}
          onAccountingUpdated={handleAccountingUpdated}
        />
      )}
    </div>
  );
}
