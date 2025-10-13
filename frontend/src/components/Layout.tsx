import React from 'react';
import { useRouter } from 'next/router';
import { useAuth } from '../contexts/AuthContext';
import Sidebar from './Sidebar';

interface LayoutProps {
  children: React.ReactNode;
  title?: string;
  showProjectsButton?: boolean;
}

export default function Layout({ children, title = 'プロジェクト一覧', showProjectsButton = true }: LayoutProps) {
  const router = useRouter();
  const { customer, logout } = useAuth();

  const handleTabChange = (tab: string) => {
    switch (tab) {
      case 'projects':
        router.push('/home');
        break;
      case 'calculator':
        router.push('/calculator');
        break;
      case 'profile':
        router.push('/profile');
        break;
      case 'settings':
        // 設定ページは将来実装
        console.log('設定ページに遷移');
        break;
      default:
        break;
    }
  };

  // 現在のパスに基づいてアクティブなタブを決定
  const getCurrentTab = () => {
    const path = router.pathname;
    if (path === '/calculator') return 'calculator';
    if (path === '/home') return 'projects';
    if (path === '/profile') return 'profile';
    return 'projects'; // デフォルト
  };

  return (
    <div className="min-h-screen flex">
      {/* サイドバー */}
      <Sidebar activeTab={getCurrentTab()} onTabChange={handleTabChange} />
      
      {/* メインコンテンツエリア */}
      <div className="flex-1 flex flex-col">
        {/* ヘッダー */}
        <header className="bg-white shadow-sm border-b">
          <div className="flex items-center justify-between px-6 py-4">
            <div>
              <h1 className="text-2xl font-semibold text-gray-900">{title}</h1>
              <p className="text-sm text-gray-600">
                ようこそ {customer?.nick_name || customer?.first_name || 'ユーザー'} さん
              </p>
            </div>
            <div className="flex items-center space-x-4">
              <button 
                onClick={() => logout()} 
                className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
              >
                ログアウト
              </button>
            </div>
          </div>
        </header>
        
        {/* メインコンテンツ */}
        <main className="flex-1 bg-gray-50">
          {children}
        </main>
      </div>
    </div>
  );
}
