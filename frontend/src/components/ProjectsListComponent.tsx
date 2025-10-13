import { useState, useEffect } from "react";
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

export default function ProjectsListComponent() {
    const [projects, setProjects] = useState<Project[]>([]);
    const [pagination, setPagination] = useState<PaginationInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [projectName, setProjectName] = useState('');
    const [projectDescription, setProjectDescription] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [addError, setAddError] = useState<string | null>(null);
    
    useEffect(() => {
        fetchProjects(currentPage);
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

    const getStatusOrder = (status: string) => {
        const orderMap: { [key: string]: number } = {
            'active': 1,
            'draft': 2,
            'completed': 3,
            'archived': 4
        };
        return orderMap[status] || 5;
    };

    const getStatusIcon = (status: string) => {
        const iconMap: { [key: string]: React.ReactElement } = {
            'active': (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            ),
            'draft': (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            ),
            'completed': (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
            'archived': (
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8l6 6 6-6" />
                </svg>
            )
        };
        return iconMap[status] || (
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        );
    };

    // プロジェクトをステータス別にグループ化
    const groupedProjects = projects.reduce((groups, project) => {
        const status = project.project_status;
        if (!groups[status]) {
            groups[status] = [];
        }
        groups[status].push(project);
        return groups;
    }, {} as Record<string, Project[]>);

    // ステータスを表示順序でソート
    const sortedStatuses = Object.keys(groupedProjects).sort((a, b) => 
        getStatusOrder(a) - getStatusOrder(b)
    );

    const handleAddProject = async () => {
        // バリデーション
        if (!projectName.trim()) {
            setAddError('プロジェクト名は必須です');
            return;
        }
        
        if (!projectDescription.trim()) {
            setAddError('プロジェクトの説明は必須です');
            return;
        }

        setIsSubmitting(true);
        setAddError(null);
        
        try {
            console.log('プロジェクト作成リクエスト:', {
                project_name: projectName.trim(),
                description: projectDescription.trim(),
                project_status: 'draft'
            });
            
            const response = await apiFetch<{ project: any }>('/api/projects', {
                method: 'POST',
                body: JSON.stringify({
                    project_name: projectName.trim(),
                    description: projectDescription.trim(),
                    project_status: 'draft'
                }),
            });
            
            console.log('プロジェクト作成レスポンス:', response);
            
            // 成功時の処理
            setProjectName('');
            setProjectDescription('');
            setIsModalOpen(false);
            
            // 成功メッセージを表示
            alert('プロジェクトが正常に作成されました！');
            
            // プロジェクト一覧を更新
            fetchProjects(currentPage);
            
        } catch (err: any) {
            console.error('プロジェクト作成エラー:', err);
            setAddError(err.message || 'プロジェクトの作成に失敗しました');
        } finally {
            setIsSubmitting(false);
        }
    };

    if (loading) {
        return (
            <div className="text-center">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                <p className="mt-4 text-gray-600">プロジェクトを読み込み中...</p>
            </div>
        );
    }

    if (error) {
        return (
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
        );
    }
    
    return (
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="mt-2 text-gray-600">登録されているプロジェクトの一覧です</p>
                    </div>
                    <button 
                        onClick={() => setIsModalOpen(true)} 
                        className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                    >
                        新しいプロジェクトを追加
                    </button>
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
                <div className="space-y-8">
                    {sortedStatuses.map((status) => (
                        <div key={status} className="bg-white rounded-lg shadow-sm border border-gray-200">
                            {/* ステータスヘッダー */}
                            <div className={`px-6 py-4 border-b ${getStatusColor(status)} rounded-t-lg`}>
                                <div className="flex items-center space-x-3">
                                    {getStatusIcon(status)}
                                    <h2 className="text-lg font-semibold">{getStatusLabel(status)}</h2>
                                    <span className="text-sm opacity-75">
                                        ({groupedProjects[status].length}件)
                                    </span>
                                </div>
                            </div>
                            
                            {/* プロジェクトカード */}
                            <div className="p-6">
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {groupedProjects[status].map((project) => (
                                        <div key={project.project_id} className="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition-colors duration-200">
                                            <div className="flex items-start justify-between mb-3">
                                                <h3 className="text-base font-semibold text-gray-900 line-clamp-2">
                                                    <a href={`/projects/${project.project_id}`} className="hover:text-blue-600 transition-colors">
                                                        {project.project_name}
                                                    </a>
                                                </h3>
                                            </div>
                                            
                                            {project.description && (
                                                <p className="text-gray-600 text-sm mb-3 line-clamp-2">
                                                    {project.description}
                                                </p>
                                            )}
                                            
                                            <div className="text-xs text-gray-500 space-y-1">
                                                <div>作成: {new Date(project.created_at).toLocaleDateString('ja-JP')}</div>
                                                <div>更新: {new Date(project.updated_at).toLocaleDateString('ja-JP')}</div>
                                            </div>
                                        </div>
                                    ))}
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

            {/* プロジェクト追加モーダル */}
            {isModalOpen && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white p-6 rounded-lg w-80 shadow-md">
                        <h2 className="text-xl mb-4">プロジェクト追加</h2>
                        
                        {/* エラーメッセージ */}
                        {addError && (
                            <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                                {addError}
                            </div>
                        )}
                        
                        <input
                            type="text"
                            value={projectName}
                            onChange={(e) => {
                                setProjectName(e.target.value);
                                if (addError) setAddError(null);
                            }}
                            placeholder="プロジェクト名"
                            className="w-full px-3 py-2 mb-4 border rounded"
                            required
                            disabled={isSubmitting}
                        />
                        <textarea
                            value={projectDescription}
                            onChange={(e) => {
                                setProjectDescription(e.target.value);
                                if (addError) setAddError(null);
                            }}
                            placeholder="プロジェクトの説明"
                            className="w-full px-3 py-2 mb-4 border rounded resize-y min-h-[100px]"
                            required
                            disabled={isSubmitting}
                        />
                        <div className="flex justify-end gap-2">
                            <button 
                                onClick={() => {
                                    setIsModalOpen(false);
                                    setAddError(null);
                                    setProjectName('');
                                    setProjectDescription('');
                                }} 
                                className="px-4 py-2 border rounded hover:bg-gray-100"
                                disabled={isSubmitting}
                            >
                                閉じる
                            </button>
                            <button 
                                onClick={handleAddProject} 
                                className="px-4 py-2 border rounded bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled={isSubmitting}
                            >
                                {isSubmitting ? '作成中...' : '追加'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}