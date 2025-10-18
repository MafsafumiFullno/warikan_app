import React, { useState, useEffect } from 'react';
import { apiFetch } from '@/lib/api';

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

interface EditAccountingModalProps {
  isOpen: boolean;
  onClose: () => void;
  projectId: number;
  taskId: number;
  members: Member[];
  accounting: Accounting | null;
  onAccountingUpdated: (accounting: Accounting) => void;
}

export default function EditAccountingModal({ 
  isOpen, 
  onClose, 
  projectId, 
  taskId, 
  members, 
  accounting, 
  onAccountingUpdated 
}: EditAccountingModalProps) {
  const [formData, setFormData] = useState({
    accounting_name: '',
    amount: '',
    description: '',
    accounting_type: 'expense',
    member_name: '',
    target_member_ids: [] as number[],
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});

  // モーダルが開かれた時に既存のデータをフォームに設定
  useEffect(() => {
    if (isOpen && accounting) {
      setFormData({
        accounting_name: accounting.task_name || '',
        amount: accounting.accounting_amount?.toString() || '',
        description: accounting.breakdown || '',
        accounting_type: accounting.accounting_type || 'expense',
        member_name: accounting.task_member_name || '',
        target_member_ids: accounting.target_member_ids || [],
      });
    }
  }, [isOpen, accounting]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    
    // バリデーションエラーをクリア
    if (validationErrors[name]) {
      setValidationErrors(prev => ({
        ...prev,
        [name]: ''
      }));
    }
  };

  const handleTargetMemberChange = (memberId: number, checked: boolean) => {
    setFormData(prev => ({
      ...prev,
      target_member_ids: checked 
        ? [...prev.target_member_ids, memberId]
        : prev.target_member_ids.filter(id => id !== memberId)
    }));
    
    // バリデーションエラーをクリア
    if (validationErrors.target_member_ids) {
      setValidationErrors(prev => ({
        ...prev,
        target_member_ids: ''
      }));
    }
  };

  const validateForm = () => {
    const errors: Record<string, string> = {};

    if (!formData.accounting_name.trim()) {
      errors.accounting_name = '会計名は必須です';
    }

    if (!formData.amount.trim()) {
      errors.amount = '金額は必須です';
    } else if (isNaN(Number(formData.amount)) || Number(formData.amount) <= 0) {
      errors.amount = '有効な金額を入力してください';
    }

    if (!formData.member_name.trim()) {
      errors.member_name = 'メンバー名は必須です';
    }

    if (formData.target_member_ids.length === 0) {
      errors.target_member_ids = '対象メンバーを選択してください';
    }

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const requestData = {
        accounting_name: formData.accounting_name.trim(),
        amount: Number(formData.amount),
        description: formData.description.trim() || undefined,
        accounting_type: formData.accounting_type,
        member_name: formData.member_name.trim(),
        target_member_ids: formData.target_member_ids,
      };

      const data = await apiFetch<{ accounting: Accounting }>(`/api/projects/${projectId}/accountings/${taskId}`, {
        method: 'PUT',
        body: JSON.stringify(requestData),
      });

      onAccountingUpdated(data.accounting);
      onClose();
    } catch (err: any) {
      setError(err.message || '会計の更新に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    onClose();
    setValidationErrors({});
    setError(null);
  };

  if (!isOpen || !accounting) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-xl font-bold text-gray-900">会計を編集</h2>
            <button
              onClick={handleClose}
              className="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {error && (
            <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label htmlFor="member_name" className="block text-sm font-medium text-gray-700 mb-1">
                メンバー名 <span className="text-red-500">*</span>
              </label>
              <select
                id="member_name"
                name="member_name"
                value={formData.member_name}
                onChange={handleInputChange}
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  validationErrors.member_name ? 'border-red-500' : 'border-gray-300'
                }`}
                required
              >
                <option value="">メンバーを選択してください</option>
                {members.map((member) => (
                  <option key={member.project_member_id} value={member.name}>
                    {member.name} {member.is_guest ? '(ゲスト)' : ''}
                  </option>
                ))}
              </select>
              {validationErrors.member_name && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.member_name}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                対象メンバー <span className="text-red-500">*</span>
              </label>
              <div className={`border rounded-md p-3 ${
                validationErrors.target_member_ids ? 'border-red-500' : 'border-gray-300'
              }`}>
                <div className="grid grid-cols-2 gap-2">
                  {members.map((member) => (
                    <label key={member.project_member_id} className="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        checked={formData.target_member_ids.includes(member.id)}
                        onChange={(e) => handleTargetMemberChange(member.id, e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                      <span className="text-sm text-gray-700">
                        {member.name} {member.is_guest ? '(ゲスト)' : ''}
                      </span>
                    </label>
                  ))}
                </div>
              </div>
              {validationErrors.target_member_ids && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.target_member_ids}</p>
              )}
            </div>

            <div>
              <label htmlFor="accounting_name" className="block text-sm font-medium text-gray-700 mb-1">
                会計名 <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                id="accounting_name"
                name="accounting_name"
                value={formData.accounting_name}
                onChange={handleInputChange}
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                  validationErrors.accounting_name ? 'border-red-500' : 'border-gray-300'
                }`}
                placeholder="例: 夕食代、交通費など"
                required
              />
              {validationErrors.accounting_name && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.accounting_name}</p>
              )}
            </div>

            <div>
              <label htmlFor="amount" className="block text-sm font-medium text-gray-700 mb-1">
                金額 <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span className="text-gray-500 sm:text-sm">¥</span>
                </div>
                <input
                  type="number"
                  id="amount"
                  name="amount"
                  value={formData.amount}
                  onChange={handleInputChange}
                  className={`w-full pl-8 pr-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                    validationErrors.amount ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="0"
                  min="1"
                  step="1"
                  required
                />
              </div>
              {validationErrors.amount && (
                <p className="mt-1 text-sm text-red-600">{validationErrors.amount}</p>
              )}
            </div>

            <div>
              <label htmlFor="accounting_type" className="block text-sm font-medium text-gray-700 mb-1">
                会計タイプ
              </label>
              <select
                id="accounting_type"
                name="accounting_type"
                value={formData.accounting_type}
                onChange={handleInputChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="expense">支出</option>
                <option value="income">収入</option>
                <option value="transfer">振替</option>
              </select>
            </div>

            <div>
              <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                説明（任意）
              </label>
              <textarea
                id="description"
                name="description"
                value={formData.description}
                onChange={handleInputChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="会計の詳細説明"
                rows={3}
              />
            </div>

            <div className="flex space-x-4 pt-4">
              <button
                type="submit"
                disabled={loading}
                className="flex-1 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {loading ? '更新中...' : '会計を更新'}
              </button>
              <button
                type="button"
                onClick={handleClose}
                className="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors"
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
