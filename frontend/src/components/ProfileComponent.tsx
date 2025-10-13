import React, { useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import RegistrationModal from '@/components/RegistrationModal';

export default function ProfileComponent() {
  const { customer, updateProfile, loading, error } = useAuth();
  const [isEditing, setIsEditing] = useState(false);
  const [showRegistrationModal, setShowRegistrationModal] = useState(false);
  const [formData, setFormData] = useState({
    first_name: customer?.first_name || '',
    last_name: customer?.last_name || '',
    nick_name: customer?.nick_name || '',
    email: customer?.email || '',
    password: '',
  });
  const [updateError, setUpdateError] = useState<string | null>(null);
  const [updateSuccess, setUpdateSuccess] = useState<string | null>(null);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setUpdateError(null);
    setUpdateSuccess(null);

    try {
      // 空のフィールドは送信しない
      const updateData: any = {};
      
      if (formData.first_name && formData.first_name.trim()) {
        updateData.first_name = formData.first_name.trim();
      }
      if (formData.last_name && formData.last_name.trim()) {
        updateData.last_name = formData.last_name.trim();
      }
      if (formData.nick_name && formData.nick_name.trim()) {
        updateData.nick_name = formData.nick_name.trim();
      }
      if (formData.email && formData.email.trim()) {
        updateData.email = formData.email.trim();
      }
      if (formData.password && formData.password.trim()) {
        updateData.password = formData.password.trim();
      }

      await updateProfile(updateData);
      setUpdateSuccess('プロフィールを更新しました');
      setIsEditing(false);
      setFormData(prev => ({ ...prev, password: '' })); // パスワードフィールドをクリア
    } catch (err: any) {
      setUpdateError(err.message || 'プロフィールの更新に失敗しました');
    }
  };

  const handleCancel = () => {
    setIsEditing(false);
    setFormData({
      first_name: customer?.first_name || '',
      last_name: customer?.last_name || '',
      nick_name: customer?.nick_name || '',
      email: customer?.email || '',
      password: '',
    });
    setUpdateError(null);
    setUpdateSuccess(null);
  };

  if (!customer) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-500 mx-auto"></div>
          <p className="mt-4 text-gray-600">読み込み中...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto p-6">
      <div className="bg-white rounded-lg shadow-md p-6">
        <div className="flex items-center justify-end mb-6">
          <div className="flex space-x-2">
            {customer.is_guest && (
              <button
                onClick={() => setShowRegistrationModal(true)}
                className="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors"
              >
                会員登録
              </button>
            )}
            {!isEditing && !customer.is_guest && (
              <button
                onClick={() => setIsEditing(true)}
                className="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors"
              >
                編集
              </button>
            )}
          </div>
        </div>

        {error && (
          <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {error}
          </div>
        )}

        {updateError && (
          <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            {updateError}
          </div>
        )}

        {updateSuccess && (
          <div className="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {updateSuccess}
          </div>
        )}

        <div className="mb-4 p-4 bg-gray-50 rounded-lg">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                ユーザータイプ
              </label>
              <div className="px-3 py-2 bg-gray-100 rounded-md">
                {customer.is_guest ? 'ゲストユーザー' : '会員'}
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                ユーザーID
              </label>
              <div className="px-3 py-2 bg-gray-100 rounded-md">
                {customer.customer_id}
              </div>
            </div>
          </div>
        </div>

        {isEditing ? (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label htmlFor="first_name" className="block text-sm font-medium text-gray-700 mb-1">
                  名前（姓）
                </label>
                <input
                  type="text"
                  id="first_name"
                  name="first_name"
                  value={formData.first_name}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="姓を入力"
                />
              </div>
              <div>
                <label htmlFor="last_name" className="block text-sm font-medium text-gray-700 mb-1">
                  名前（名）
                </label>
                <input
                  type="text"
                  id="last_name"
                  name="last_name"
                  value={formData.last_name}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="名を入力"
                />
              </div>
            </div>

            <div>
              <label htmlFor="nick_name" className="block text-sm font-medium text-gray-700 mb-1">
                ニックネーム
              </label>
              <input
                type="text"
                id="nick_name"
                name="nick_name"
                value={formData.nick_name}
                onChange={handleInputChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="ニックネームを入力"
                required
              />
            </div>

            {!customer.is_guest && (
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                  メールアドレス
                </label>
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="メールアドレスを入力"
                />
              </div>
            )}

            {!customer.is_guest && (
              <div>
                <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                  新しいパスワード（変更する場合のみ入力）
                </label>
                <input
                  type="password"
                  id="password"
                  name="password"
                  value={formData.password}
                  onChange={handleInputChange}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="新しいパスワードを入力（8文字以上）"
                  minLength={8}
                />
              </div>
            )}

            <div className="flex space-x-4 pt-4">
              <button
                type="submit"
                disabled={loading}
                className="px-6 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {loading ? '更新中...' : '更新'}
              </button>
              <button
                type="button"
                onClick={handleCancel}
                className="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors"
              >
                キャンセル
              </button>
            </div>
          </form>
        ) : (
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  名前（姓）
                </label>
                <div className="px-3 py-2 bg-gray-100 rounded-md">
                  {customer.first_name || '未設定'}
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  名前（名）
                </label>
                <div className="px-3 py-2 bg-gray-100 rounded-md">
                  {customer.last_name || '未設定'}
                </div>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                ニックネーム
              </label>
              <div className="px-3 py-2 bg-gray-100 rounded-md">
                {customer.nick_name || '未設定'}
              </div>
            </div>

            {!customer.is_guest && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  メールアドレス
                </label>
                <div className="px-3 py-2 bg-gray-100 rounded-md">
                  {customer.email || '未設定'}
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      <RegistrationModal
        isOpen={showRegistrationModal}
        onClose={() => setShowRegistrationModal(false)}
      />
    </div>
  );
}
