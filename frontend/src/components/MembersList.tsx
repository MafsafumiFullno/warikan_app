import { useState, useEffect } from 'react';
import { apiFetch } from '@/lib/api';
import AddMemberModal from './AddMemberModal';

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

interface MembersListProps {
  projectId: number;
  currentUserId: number;
  isOwner: boolean;
  onMemberAdded?: (member: Member) => void;
  onMemberRemoved?: (memberId: number) => void;
}

export default function MembersList({ 
  projectId, 
  currentUserId, 
  isOwner, 
  onMemberAdded, 
  onMemberRemoved 
}: MembersListProps) {
  const [members, setMembers] = useState<Member[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingWeight, setEditingWeight] = useState<number | null>(null);
  const [weightValue, setWeightValue] = useState<string>('');
  const [editingMemo, setEditingMemo] = useState<number | null>(null);
  const [memoValue, setMemoValue] = useState<string>('');

  useEffect(() => {
    fetchMembers();
  }, [projectId]);

  const fetchMembers = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await apiFetch<{ members: Member[] }>(`/api/projects/${projectId}/members`);
      setMembers(response.members);
      
    } catch (err: any) {
      console.error('メンバー一覧取得エラー:', err);
      setError(err.message || 'メンバー一覧の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  const handleMemberAdded = (newMember: Member) => {
    // メンバー一覧を再取得して最新の状態を反映
    fetchMembers();
    onMemberAdded?.(newMember);
    setShowAddForm(false);
  };

  const handleMemberRemoved = async (memberId: number) => {
    try {
      await apiFetch(`/api/projects/${projectId}/members/${memberId}`, {
        method: 'DELETE',
      });
      
      setMembers(prev => prev.filter(member => member.id !== memberId));
      onMemberRemoved?.(memberId);
      
    } catch (err: any) {
      console.error('メンバー削除エラー:', err);
      alert('メンバーの削除に失敗しました');
    }
  };

  const handleWeightUpdate = async (memberId: number, newWeight: number) => {
    try {
      await apiFetch(`/api/projects/${projectId}/members/${memberId}/split-weight`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          split_weight: newWeight,
        }),
      });
      
      setMembers(prev => prev.map(member => 
        member.id === memberId 
          ? { ...member, split_weight: newWeight }
          : member
      ));
      
      setEditingWeight(null);
      setWeightValue('');
      
    } catch (err: any) {
      console.error('割り勘比重更新エラー:', err);
      alert('割り勘比重の更新に失敗しました');
    }
  };

  const startWeightEdit = (memberId: number, currentWeight: number) => {
    setEditingWeight(memberId);
    setWeightValue(currentWeight.toString());
  };

  const cancelWeightEdit = () => {
    setEditingWeight(null);
    setWeightValue('');
  };

  const handleMemoUpdate = async (memberId: number, newMemo: string) => {
    try {
      await apiFetch(`/api/projects/${projectId}/members/${memberId}/memo`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          memo: newMemo,
        }),
      });
      
      setMembers(prev => prev.map(member => 
        member.id === memberId 
          ? { ...member, memo: newMemo }
          : member
      ));
      
      setEditingMemo(null);
      setMemoValue('');
      
    } catch (err: any) {
      console.error('メモ更新エラー:', err);
      alert('メモの更新に失敗しました');
    }
  };

  const startMemoEdit = (memberId: number, currentMemo: string) => {
    setEditingMemo(memberId);
    setMemoValue(currentMemo || '');
  };

  const cancelMemoEdit = () => {
    setEditingMemo(null);
    setMemoValue('');
  };

  const getRoleLabel = (role: string, roleName: string) => {
    return roleName || (role === 'owner' ? 'オーナー' : 'メンバー');
  };

  const getRoleColor = (role: string) => {
    return role === 'owner' 
      ? 'bg-purple-100 text-purple-800' 
      : 'bg-blue-100 text-blue-800';
  };

  if (loading) {
    return (
      <div className="bg-white shadow rounded-lg">
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg font-medium text-gray-900">メンバー</h2>
        </div>
        <div className="px-6 py-4">
          <div className="text-center py-8">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            <p className="mt-2 text-gray-600">メンバーを読み込み中...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white shadow rounded-lg">
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg font-medium text-gray-900">メンバー</h2>
        </div>
        <div className="px-6 py-4">
          <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <p className="font-bold">エラーが発生しました</p>
            <p>{error}</p>
            <button
              onClick={fetchMembers}
              className="mt-2 bg-red-600 hover:bg-red-700 text-white font-medium py-1 px-3 rounded text-sm"
            >
              再試行
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white shadow rounded-lg">
      <div className="px-6 py-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-medium text-gray-900">メンバー</h2>
          {isOwner && (
            <button
              onClick={() => setShowAddForm(true)}
              className="bg-blue-600 hover:bg-blue-700 text-white font-medium py-1 px-3 rounded text-sm"
            >
              メンバー追加
            </button>
          )}
        </div>
      </div>
      <div className="px-6 py-4">
        {members.length === 0 ? (
          <div className="text-center text-gray-500 py-8">
            <div className="text-4xl mb-4">👥</div>
            <p>メンバーがまだ追加されていません</p>
            {isOwner && (
              <p className="text-sm mt-2">「メンバー追加」ボタンからメンバーを追加してください</p>
            )}
          </div>
        ) : (
          <div className="space-y-3">
            {members.map((member) => (
              <div key={member.id} className="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                <div className="flex-1">
                  <div className="flex items-center space-x-2">
                    <h3 className="font-medium text-gray-900">{member.name}</h3>
                    <span className={`px-2 py-1 text-xs rounded-full ${getRoleColor(member.role)}`}>
                      {getRoleLabel(member.role, member.role_name)}
                    </span>
                    {member.is_guest && (
                      <span className="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                        ゲスト
                      </span>
                    )}
                  </div>
                  {member.email && (
                    <p className="text-sm text-gray-600 mt-1">{member.email}</p>
                  )}
                  <p className="text-xs text-gray-500 mt-1">
                    参加日: {new Date(member.joined_at).toLocaleDateString('ja-JP')}
                  </p>
                  <div className="flex items-center space-x-4 mt-2">
                    <div className="flex items-center space-x-2">
                      <span className="text-xs text-gray-500">割り勘比重:</span>
                      {editingWeight === member.id ? (
                        <div className="flex items-center space-x-1">
                          <input
                            type="number"
                            value={weightValue}
                            onChange={(e) => setWeightValue(e.target.value)}
                            className="w-16 px-1 py-0.5 text-xs border border-gray-300 rounded"
                            min="0.01"
                            max="999.99"
                            step="0.01"
                          />
                          <button
                            onClick={() => handleWeightUpdate(member.id, parseFloat(weightValue))}
                            className="text-xs bg-blue-600 text-white px-2 py-0.5 rounded hover:bg-blue-700"
                          >
                            保存
                          </button>
                          <button
                            onClick={cancelWeightEdit}
                            className="text-xs bg-gray-600 text-white px-2 py-0.5 rounded hover:bg-gray-700"
                          >
                            キャンセル
                          </button>
                        </div>
                      ) : (
                        <div className="flex items-center space-x-1">
                          <span className="text-sm font-medium">{member.split_weight}</span>
                          {isOwner && (
                            <button
                              onClick={() => startWeightEdit(member.id, member.split_weight)}
                              className="text-xs text-blue-600 hover:text-blue-800"
                            >
                              編集
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                    <div className="flex items-center space-x-2">
                      <span className="text-xs text-gray-500">支出合計:</span>
                      <span className="text-sm font-semibold text-red-600">
                        ¥{(member.total_expense || 0).toLocaleString()}
                      </span>
                    </div>
                  </div>
                  {member.memo && (
                    <div className="mt-2">
                      <span className="text-xs text-gray-500">メモ:</span>
                      <p className="text-sm text-gray-700 mt-1">{member.memo}</p>
                    </div>
                  )}
                  {isOwner && (
                    <div className="mt-2">
                      {editingMemo === member.id ? (
                        <div className="space-y-2">
                          <textarea
                            value={memoValue}
                            onChange={(e) => setMemoValue(e.target.value)}
                            className="w-full px-2 py-1 text-sm border border-gray-300 rounded resize-none"
                            rows={2}
                            placeholder="メモを入力"
                            maxLength={1000}
                          />
                          <div className="flex space-x-1">
                            <button
                              onClick={() => handleMemoUpdate(member.id, memoValue)}
                              className="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700"
                            >
                              保存
                            </button>
                            <button
                              onClick={cancelMemoEdit}
                              className="text-xs bg-gray-600 text-white px-2 py-1 rounded hover:bg-gray-700"
                            >
                              キャンセル
                            </button>
                          </div>
                        </div>
                      ) : (
                        <button
                          onClick={() => startMemoEdit(member.id, member.memo || '')}
                          className="text-xs text-blue-600 hover:text-blue-800"
                        >
                          {member.memo ? 'メモを編集' : 'メモを追加'}
                        </button>
                      )}
                    </div>
                  )}
                </div>
                {isOwner && member.role !== 'owner' && (
                  <button
                    onClick={() => {
                      if (confirm(`${member.name}をメンバーから削除しますか？`)) {
                        handleMemberRemoved(member.id);
                      }
                    }}
                    className="text-red-600 hover:text-red-800 text-sm font-medium"
                  >
                    削除
                  </button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* メンバー追加モーダル */}
      <AddMemberModal
        isOpen={showAddForm}
        onClose={() => setShowAddForm(false)}
        projectId={projectId}
        onMemberAdded={handleMemberAdded}
      />
    </div>
  );
}
