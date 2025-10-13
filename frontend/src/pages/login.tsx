import { useState } from 'react';
import { useRouter } from 'next/router';
import Link from 'next/link';
import { useAuth } from '@/contexts/AuthContext';

const validateEmail = (email: string): string | null => {
  if (!email.trim()) {
    return 'メールアドレスを入力してください';
  }
  
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    return '正しいメールアドレス形式で入力してください';
  }
  
  return null;
};

const validatePassword = (password: string): string | null => {
  if (!password) {
    return 'パスワードを入力してください';
  }
  
  if (password.length < 6) {
    return 'パスワードは6文字以上で入力してください';
  }
  
  return null;
};

export default function LoginPage() {
  const router = useRouter();
  const { guestLogin, login, loading, error } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [emailError, setEmailError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [authError, setAuthError] = useState<string | null>(null);

  const onQuickStart = async () => {
    // 自動的にニックネームを生成してゲストログイン
    const adjectives = ['楽しい', '元気な', '素敵な', '素晴らしい', '素朴な', '勇敢な', '賢い', '優しい'];
    const animals = ['ねこ', 'いぬ', 'うさぎ', 'パンダ', 'ライオン', 'ぞう', 'きつね', 'たぬき'];
    
    const adjective = adjectives[Math.floor(Math.random() * adjectives.length)];
    const animal = animals[Math.floor(Math.random() * animals.length)];
    const number = Math.floor(Math.random() * 999) + 1;
    const generatedNickname = `${adjective}${animal}${number}`;
    
    await guestLogin(generatedNickname);
    router.push('/home');
  };

  const onLogin = async () => {
    // 認証エラーをクリア
    setAuthError(null);
    
    // バリデーション実行
    const emailValidationError = validateEmail(email);
    const passwordValidationError = validatePassword(password);
    
    setEmailError(emailValidationError);
    setPasswordError(passwordValidationError);
    
    // バリデーションエラーがある場合は処理を停止
    if (emailValidationError || passwordValidationError) {
      return;
    }
    
    try {
      await login({ email, password });
      router.push('/home');
    } catch (loginError: any) {
      // 認証エラーの場合は専用のstateに設定
      if (loginError.message && loginError.message.includes('メールアドレスまたはパスワードが正しくありません')) {
        setAuthError('メールアドレスまたはパスワードが間違っている可能性があります。');
      }
      // その他のエラーは既存のerror stateで表示される
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
          割り勘アプリ
        </h2>
        <p className="mt-2 text-center text-sm text-gray-600">
          プロジェクトを作成して友達と割り勘を計算しましょう
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
          {/* 登録せずに始める */}
          <div className="mb-8">
            <button
              onClick={onQuickStart}
              disabled={loading}
              className="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {loading ? (
                <div className="flex items-center">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  開始中...
                </div>
              ) : (
                '登録せずに始める'
              )}
            </button>
            <p className="mt-2 text-center text-xs text-gray-500">
              ワンクリックでアプリを開始できます
            </p>
          </div>

          {/* 区切り線 */}
          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-gray-300" />
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="px-2 bg-white text-gray-500">または</span>
            </div>
          </div>

          {/* ログイン・登録フォーム */}
          <div className="mt-8">
            <h3 className="text-lg font-medium text-gray-900 mb-4 text-center">
              アカウントでログイン
            </h3>
            
            <div className="space-y-4">
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                  メールアドレス
                </label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  className={`mt-1 appearance-none block w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    emailError ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="example@email.com"
                  value={email}
                  onChange={(e) => {
                    setEmail(e.target.value);
                    // 入力時にエラーをクリア
                    if (emailError) {
                      setEmailError(null);
                    }
                    if (authError) {
                      setAuthError(null);
                    }
                  }}
                />
                {emailError && (
                  <p className="mt-1 text-sm text-red-600">{emailError}</p>
                )}
              </div>

              <div>
                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                  パスワード
                </label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  className={`mt-1 appearance-none block w-full px-3 py-2 border rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm ${
                    passwordError ? 'border-red-300 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="パスワード"
                  value={password}
                  onChange={(e) => {
                    setPassword(e.target.value);
                    // 入力時にエラーをクリア
                    if (passwordError) {
                      setPasswordError(null);
                    }
                    if (authError) {
                      setAuthError(null);
                    }
                  }}
                />
                {passwordError && (
                  <p className="mt-1 text-sm text-red-600">{passwordError}</p>
                )}
              </div>

              <div className="flex gap-3">
                <button 
                  type="button"
                  onClick={onLogin} 
                  disabled={loading}
                  className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  ログイン
                </button>
                <Link href="/register" className="flex-1">
                  <button className="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition-colors">
                    新規登録
                  </button>
                </Link>
              </div>
              
              {/* 認証エラー表示 */}
              {authError && (
                <div className="mt-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded">
                  <p className="text-sm text-center">{authError}</p>
                </div>
              )}
            </div>
          </div>

          {/* エラー表示 */}
          {error && (
            <div className="mt-6 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded">
              <p className="text-sm">{error}</p>
            </div>
          )}
        </div>
      </div>

    </div>
  );
}
