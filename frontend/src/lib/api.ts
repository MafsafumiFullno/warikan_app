export const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000';

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

function getAuthToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('auth_token');
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getAuthToken();
  
  // CSRFトークンを取得（POST, PUT, PATCH, DELETEリクエストの場合）
  let csrfToken = '';
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method || 'GET')) {
    try {
      const csrfResponse = await fetch(`${API_BASE_URL}/api/csrf-token`, {
        credentials: 'include',
      });
      if (csrfResponse.ok) {
        const csrfData = await csrfResponse.json();
        csrfToken = csrfData.csrf_token;
      }
    } catch (error) {
      console.warn('CSRFトークンの取得に失敗しました:', error);
    }
  }
  
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrfToken,
    ...(options.headers || {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };

  const res = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
    credentials: 'include',
  });

  if (!res.ok) {
    let detail: any = undefined;
    try {
      detail = await res.json();
    } catch {}
    console.error('API Error:', {
      status: res.status,
      statusText: res.statusText,
      url: `${API_BASE_URL}${path}`,
      detail
    });
    throw new Error(detail?.message || `API Error: ${res.status}`);
  }

  // 204 No Content の場合
  if (res.status === 204) return undefined as unknown as T;

  return (await res.json()) as T;
}
