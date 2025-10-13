import AuthGuard from '@/components/AuthGuard';
import Layout from '@/components/Layout';
import ProjectsListComponent from '@/components/ProjectsListComponent';

export default function ProjectsList() {
  return (
    <AuthGuard>
      <Layout title="プロジェクト一覧" showProjectsButton={true}>
        <ProjectsListComponent />
      </Layout>
    </AuthGuard>
  );
}
