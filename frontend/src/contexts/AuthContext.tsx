import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { apiFetch } from '@/lib/api';

export type Customer = {
  customer_id: number;
  is_guest: boolean;
  first_name?: string | null;
  last_name?: string | null;
  nick_name?: string | null;
  email?: string | null;
};

type AuthState = {
  customer: Customer | null;
  token: string | null;
  loading: boolean;
  error: string | null;
};

type AuthContextType = AuthState & {
  guestLogin: (nickName: string) => Promise<void>;
  register: (payload: { email: string; password: string; first_name?: string; last_name?: string; nick_name?: string }) => Promise<void>;
  upgradeToMember: (payload: { email: string; password: string; first_name?: string; last_name?: string; nick_name?: string }) => Promise<void>;
  login: (payload: { email: string; password: string }) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
  updateProfile: (payload: { first_name?: string; last_name?: string; nick_name?: string; email?: string; password?: string }) => Promise<void>;
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const saveToken = useCallback((t: string | null) => {
    setToken(t);
    if (typeof window !== 'undefined') {
      if (t) localStorage.setItem('auth_token', t);
      else localStorage.removeItem('auth_token');
    }
  }, []);

  const guestLogin = useCallback(async (nickName: string) => {
    setError(null);
    setLoading(true);
    try {
      const payload = nickName.trim() ? { nick_name: nickName.trim() } : { nick_name: 'ゲストユーザー' };
      console.log('ゲストログインリクエスト:', payload);
      
      const res = await apiFetch<{ customer: Customer; token: string }>(`/api/auth/guest-login`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      
      console.log('ゲストログインレスポンス:', res);
      setCustomer(res.customer);
      saveToken(res.token);
    } catch (e: any) {
      console.error('ゲストログインエラー:', e);
      setError(e.message || 'ログインに失敗しました');
      throw e;
    } finally {
      setLoading(false);
    }
  }, [saveToken]);

  const register = useCallback(async (payload: { email: string; password: string; first_name?: string; last_name?: string; nick_name?: string }) => {
    setError(null);
    setLoading(true);
    try {
      const res = await apiFetch<{ customer: Customer; token: string }>(`/api/auth/register`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setCustomer(res.customer);
      saveToken(res.token);
    } catch (e: any) {
      setError(e.message || '登録に失敗しました');
      throw e;
    } finally {
      setLoading(false);
    }
  }, [saveToken]);

  const upgradeToMember = useCallback(async (payload: { email: string; password: string; first_name?: string; last_name?: string; nick_name?: string }) => {
    setError(null);
    setLoading(true);
    try {
      const res = await apiFetch<{ customer: Customer }>(`/api/auth/upgrade-to-member`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setCustomer(res.customer);
    } catch (e: any) {
      setError(e.message || '会員登録に失敗しました');
      throw e;
    } finally {
      setLoading(false);
    }
  }, []);

  const login = useCallback(async (payload: { email: string; password: string }) => {
    setError(null);
    setLoading(true);
    try {
      const res = await apiFetch<{ customer: Customer; token: string }>(`/api/auth/login`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setCustomer(res.customer);
      saveToken(res.token);
    } catch (e: any) {
      setError(e.message || 'ログインに失敗しました');
      throw e;
    } finally {
      setLoading(false);
    }
  }, [saveToken]);

  const logout = useCallback(async () => {
    setError(null);
    setLoading(true);
    try {
      await apiFetch(`/api/auth/logout`, { method: 'POST' });
    } catch {}
    finally {
      saveToken(null);
      setCustomer(null);
      setLoading(false);
    }
  }, [saveToken]);

  const refreshMe = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch<{ customer: Customer }>(`/api/auth/me`);
      setCustomer(res.customer);
    } catch {
      // トークン不正など
      saveToken(null);
      setCustomer(null);
    } finally {
      setLoading(false);
    }
  }, [saveToken]);

  const updateProfile = useCallback(async (payload: { first_name?: string; last_name?: string; nick_name?: string; email?: string; password?: string }) => {
    setError(null);
    setLoading(true);
    try {
      const res = await apiFetch<{ customer: Customer }>(`/api/auth/profile`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      setCustomer(res.customer);
    } catch (e: any) {
      setError(e.message || 'プロフィール更新に失敗しました');
      throw e;
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const stored = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
    if (stored) {
      saveToken(stored);
      refreshMe();
    } else {
      setLoading(false);
    }
  }, [refreshMe, saveToken]);

  const value = useMemo<AuthContextType>(() => ({
    customer, token, loading, error,
    guestLogin, register, upgradeToMember, login, logout, refreshMe, updateProfile,
  }), [customer, token, loading, error, guestLogin, register, upgradeToMember, login, logout, refreshMe, updateProfile]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
