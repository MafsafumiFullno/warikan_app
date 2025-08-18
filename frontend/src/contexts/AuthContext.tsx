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
  oauthLogin: (payload: {
    provider_name: string;
    provider_user_id: string;
    access_token: string;
    refresh_token?: string;
    token_expired_date?: string;
    email?: string;
    first_name?: string;
    last_name?: string;
  }) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
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
      const res = await apiFetch<{ customer: Customer; token: string }>(`/api/auth/guest-login`, {
        method: 'POST',
        body: JSON.stringify({ nick_name: nickName }),
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

  const oauthLogin = useCallback(async (payload: any) => {
    setError(null);
    setLoading(true);
    try {
      const res = await apiFetch<{ customer: Customer; token: string }>(`/api/auth/oauth-login`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setCustomer(res.customer);
      saveToken(res.token);
    } catch (e: any) {
      setError(e.message || 'OAuthログインに失敗しました');
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
    guestLogin, oauthLogin, logout, refreshMe,
  }), [customer, token, loading, error, guestLogin, oauthLogin, logout, refreshMe]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
