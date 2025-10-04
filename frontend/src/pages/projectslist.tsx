import { useState, useEffect } from "react";
import Link from 'next/link';
import AuthGuard from '@/components/AuthGuard';
import Layout from '@/components/Layout';
import { apiFetch } from "../lib/api";

interface Project {
    project_id: number;
    project_name: string;
    description?: string;
    project_status: string;
    created_at: string;
    updated_at: string;
}

interface PaginationInfo {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface ProjectsResponse {
    projects: Project[];
    pagination: PaginationInfo;
}

export default function ProjectsList() {
    const [projects, setProjects] = useState<Project[]>([]);
    const [pagination, setPagination] = useState<PaginationInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    
    useEffect(() => {
        fetchProjects(currentPage);
    }, [currentPage]);

    // ページが表示されるたびにプロジェクト一覧を更新
    useEffect(() => {
        const handleVisibilityChange = () => {
            if (!document.hidden) {
                fetchProjects(currentPage);
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);
        return () => {
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };
    }, [currentPage]);

    const fetchProjects = async (page: number = 1) => {
        try {
            setLoading(true);
            setError(null);
            const response = await apiFetch<ProjectsResponse>(`/api/projects?page=${page}`);
            setProjects(response.projects);
            setPagination(response.pagination);
        } catch (err) {
            console.error('プロジェクトの取得に失敗しました:', err);
            setError(err instanceof Error ? err.message : 'プロジェクトの取得に失敗しました');
        } finally {
            setLoading(false);
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

    if (loading) {
        return (
            <div className="min-h-screen bg-gray-50 py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                        <p className="mt-4 text-gray-600">プロジェクトを読み込み中...</p>
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen bg-gray-50 py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center">
                        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <p className="font-bold">エラーが発生しました</p>
                            <p>{error}</p>
                        </div>
                        <button
                            onClick={() => fetchProjects(currentPage)}
                            className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                        >
                            再試行
                        </button>
                    </div>
                </div>
            </div>
        );
    }
    
    return (
        <AuthGuard>
            <Layout title="プロジェクト一覧" showProjectsButton={true}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">プロジェクト一覧</h1>
                            <p className="mt-2 text-gray-600">登録されているプロジェクトの一覧です</p>
                        </div>
                        <div className="flex space-x-3">
                            <button
                                onClick={() => fetchProjects(currentPage)}
                                className="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md transition-colors"
                                title="一覧を更新"
                            >
                                🔄 更新
                            </button>
                            <Link href="/projects/create">
                                <button className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition-colors">
                                    + 新しいプロジェクト
                                </button>
                            </Link>
                        </div>
                    </div>

                {projects.length === 0 ? (
                    <div className="text-center py-12">
                        <div className="text-gray-400 mb-4">
                            <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">プロジェクトがありません</h3>
                        <p className="text-gray-500">新しいプロジェクトを作成してください</p>
                    </div>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {projects.map((project) => (
                            <div key={project.project_id} className="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                                <div className="p-6">
                                    <div className="flex items-start justify-between mb-4">
                            <h3 className="text-lg font-semibold text-gray-900 line-clamp-2">
                              <Link href={`/projects/${project.project_id}`} className="hover:text-blue-600 transition-colors">
                                {project.project_name}
                              </Link>
                            </h3>
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(project.project_status)}`}>
                                            {getStatusLabel(project.project_status)}
                                        </span>
                                    </div>
                                    
                                    {project.description && (
                                        <p className="text-gray-600 text-sm mb-4 line-clamp-3">
                                            {project.description}
                                        </p>
                                    )}
                                    
                                    <div className="flex items-center justify-between text-xs text-gray-500">
                                        <span>作成日: {new Date(project.created_at).toLocaleDateString('ja-JP')}</span>
                                        <span>更新日: {new Date(project.updated_at).toLocaleDateString('ja-JP')}</span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* ページネーション */}
                {pagination && pagination.last_page > 1 && (
                    <div className="mt-8 flex justify-center">
                        <nav className="flex items-center space-x-2">
                            {/* 前のページ */}
                            <button
                                onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                                disabled={currentPage === 1}
                                className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                前へ
                            </button>

                            {/* ページ番号 */}
                            {Array.from({ length: Math.min(5, pagination.last_page) }, (_, i) => {
                                const pageNum = Math.max(1, Math.min(pagination.last_page - 4, currentPage - 2)) + i;
                                if (pageNum > pagination.last_page) return null;
                                
                                return (
                                    <button
                                        key={pageNum}
                                        onClick={() => setCurrentPage(pageNum)}
                                        className={`px-3 py-2 text-sm font-medium rounded-md ${
                                            currentPage === pageNum
                                                ? 'bg-blue-600 text-white'
                                                : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'
                                        }`}
                                    >
                                        {pageNum}
                                    </button>
                                );
                            })}

                            {/* 次のページ */}
                            <button
                                onClick={() => setCurrentPage(Math.min(pagination.last_page, currentPage + 1))}
                                disabled={currentPage === pagination.last_page}
                                className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                次へ
                            </button>
                        </nav>
                    </div>
                )}

                {/* 件数表示 */}
                {pagination && (
                    <div className="mt-4 text-center text-sm text-gray-500">
                        {pagination.from} - {pagination.to} / {pagination.total} 件
                    </div>
                )}
                </div>
            </Layout>
        </AuthGuard>
    );
}