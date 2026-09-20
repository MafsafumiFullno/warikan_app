export const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000';

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

const MUTATING_METHODS = new Set<HttpMethod>(['POST', 'PUT', 'PATCH', 'DELETE']);

// CSRFトークンはセッション単位で再利用し、更新系リクエストごとの余分な取得を避ける。
let cachedCsrfToken: string | null = null;
let csrfTokenRequest: Promise<string | null> | null = null;

function getAuthToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('auth_token');
}

function normalizeMethod(method?: string): HttpMethod {
  return (method || 'GET').toUpperCase() as HttpMethod;
}

function isMutatingMethod(method: HttpMethod): boolean {
  return MUTATING_METHODS.has(method);
}

// バックエンドから新しいCSRFトークンを取得する。キャッシュ判断は呼び出し側で行う。
async function fetchFreshCsrfToken(): Promise<string | null> {
  try {
    const csrfResponse = await fetch(`${API_BASE_URL}/api/csrf-token`, {
      credentials: 'include',
    });

    if (!csrfResponse.ok) {
      return null;
    }

    const csrfData = await csrfResponse.json();
    return csrfData.csrf_token ?? null;
  } catch (error) {
    console.warn('CSRFトークンの取得に失敗しました:', error);
    return null;
  }
}

// 更新系リクエストで利用するCSRFトークンを返す。必要な場合だけ再取得する。
async function getCachedCsrfToken(forceRefresh = false): Promise<string | null> {
  if (!forceRefresh && cachedCsrfToken) {
    return cachedCsrfToken;
  }

  if (forceRefresh) {
    cachedCsrfToken = null;
    csrfTokenRequest = null;
  }

  // 同時に複数の更新系リクエストが走っても、CSRF取得は1回にまとめる。
  csrfTokenRequest ??= fetchFreshCsrfToken();

  const token = await csrfTokenRequest;
  csrfTokenRequest = null;
  cachedCsrfToken = token;

  return token;
}

// 呼び出し側のヘッダーを尊重しつつ、API共通のJSON/CSRF/認証ヘッダーを補う。
function buildApiHeaders(options: RequestInit, csrfToken: string | null): Headers {
  const token = getAuthToken();
  const headers = new Headers(options.headers);

  if (!headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  if (csrfToken) {
    headers.set('X-CSRF-TOKEN', csrfToken);
  }

  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  return headers;
}

// apiFetch本体から使う低レベルなfetch実行。レスポンス解釈やリトライはここでは行わない。
async function sendApiRequest(path: string, options: RequestInit, csrfToken: string | null): Promise<Response> {
  return fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: buildApiHeaders(options, csrfToken),
    credentials: 'include',
  });
}

// APIのエラーレスポンスを利用者に見せるErrorへ変換する。
async function buildApiError(res: Response, path: string): Promise<Error> {
  let detail: any = undefined;
  try {
    const text = await res.text();
    if (text) {
      detail = JSON.parse(text);
    }
  } catch (e) {
    // JSONパースに失敗した場合は、テキストをそのまま使用
    detail = { message: `API Error: ${res.status} ${res.statusText}` };
  }
  console.error('API Error:', {
    status: res.status,
    statusText: res.statusText,
    url: `${API_BASE_URL}${path}`,
    detail
  });
  const errorMessage = detail?.message || detail?.error || `API Error: ${res.status} ${res.statusText}`;
  return new Error(errorMessage);
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = normalizeMethod(options.method);
  let csrfToken = isMutatingMethod(method) ? await getCachedCsrfToken() : null;
  let res = await sendApiRequest(path, options, csrfToken);

  // LaravelのCSRFトークン期限切れに備え、419の場合だけ再取得して1回だけ再送する。
  if (res.status === 419 && isMutatingMethod(method)) {
    csrfToken = await getCachedCsrfToken(true);
    res = await sendApiRequest(path, options, csrfToken);
  }

  if (!res.ok) {
    throw await buildApiError(res, path);
  }

  // 204 No Content の場合
  if (res.status === 204) return undefined as unknown as T;

  return (await res.json()) as T;
}
