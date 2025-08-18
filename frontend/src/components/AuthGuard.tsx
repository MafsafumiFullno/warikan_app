import { useRouter } from 'next/router';
import { useEffect } from 'react';
import { useAuth } from '@/contexts/AuthContext';

export default function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { customer, loading } = useAuth();

  useEffect(() => {
    if (!loading && !customer) {
      router.replace('/login');
    }
  }, [customer, loading, router]);

  if (loading) {
    return <div className="p-6">Loading...</div>;
  }

  if (!customer) return null;
  return <>{children}</>;
}
