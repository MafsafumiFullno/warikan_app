import AuthGuard from '@/components/AuthGuard';
import Layout from '@/components/Layout';
import CalculatorComponent from '@/components/CalculatorComponent';

export default function Calculator() {
  return (
    <AuthGuard>
      <Layout title="電卓" showProjectsButton={false}>
        <CalculatorComponent />
      </Layout>
    </AuthGuard>
  );
}