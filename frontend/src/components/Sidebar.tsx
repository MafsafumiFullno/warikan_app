import React from 'react';
import Link from 'next/link';

interface SidebarProps {
  activeTab: string;
  onTabChange: (tab: string) => void;
}

interface SidebarItem {
  id: string;
  label: string;
  href?: string;
  icon: React.ReactElement;
}

const SidebarItem = ({ item, isActive, onTabChange }: {
  item: SidebarItem;
  isActive: boolean;
  onTabChange: (tab: string) => void;
}) => {
  const className = `w-full px-4 py-3 text-left flex items-center space-x-3 transition-colors duration-200 ${
    isActive
      ? 'bg-blue-100 text-blue-700 border-r-2 border-blue-700'
      : 'text-gray-700 hover:bg-gray-100'
  }`;

  const handleClick = () => {
    onTabChange(item.id);
  };

  return (
    <button onClick={handleClick} className={className}>
      {item.icon}
      <span className="font-medium">{item.label}</span>
    </button>
  );
};

export default function Sidebar({ activeTab, onTabChange }: SidebarProps) {
  const sidebarItems: SidebarItem[] = [
    {
      id: 'projects',
      label: 'プロジェクト一覧',
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
      ),
    },
    {
      id: 'calculator',
      label: '電卓',
      href: '/calculator',
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 -c4l4 4m-4-4l-4 4m0 2h2m8 0h2" />
        </svg>
      ),
    },
    {
      id: 'settings',
      label: '設定',
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      ),
    },
  ];

  return (
    <div className="w-64 bg-white shadow-lg">
      <div className="p-4">
        <h2 className="text-lg font-semibold text-gray-900">割り勘アプリ</h2>
      </div>
      <nav className="mt-2">
        {sidebarItems.map((item) => (
          <SidebarItem
            key={item.id}
            item={item}
            isActive={activeTab === item.id}
            onTabChange={onTabChange}
          />
        ))}
      </nav>
    </div>
  );
}
