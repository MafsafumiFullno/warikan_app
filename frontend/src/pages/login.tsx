import { useState } from 'react';
import { useRouter } from 'next/router';
import { useAuth } from '@/contexts/AuthContext';

export default function LoginPage() {
  const router = useRouter();
  const { guestLogin, oauthLogin, loading, error } = useAuth();
  const [nickName, setNickName] = useState('');

  const [providerName, setProviderName] = useState('google');
  const [providerUserId, setProviderUserId] = useState('sample_user');
  const [accessToken, setAccessToken] = useState('dummy_access_token');

  const onGuestLogin = async () => {
    await guestLogin(nickName || 'ゲスト');
    router.push('/home');
  };

  const onOAuthLogin = async () => {
    await oauthLogin({
      provider_name: providerName,
      provider_user_id: providerUserId,
      access_token: accessToken,
    });
    router.push('/home');
  };

  return (
    <main className="flex flex-col items-center justify-center min-h-screen gap-8 p-6">
      <h1 className="text-2xl font-bold">ログイン</h1>

      <section className="w-80 p-4 border rounded">
        <h2 className="font-semibold mb-2">ゲストログイン</h2>
        <input
          className="w-full border rounded p-2 mb-2"
          placeholder="ニックネーム"
          value={nickName}
          onChange={(e) => setNickName(e.target.value)}
        />
        <button className="w-full border rounded p-2 bg-blue-500 text-white" onClick={onGuestLogin} disabled={loading}>
          ゲストとして入る
        </button>
      </section>

      <section className="w-80 p-4 border rounded">
        <h2 className="font-semibold mb-2">OAuthログイン（暫定）</h2>
        <input className="w-full border rounded p-2 mb-2" placeholder="provider_name" value={providerName} onChange={(e) => setProviderName(e.target.value)} />
        <input className="w-full border rounded p-2 mb-2" placeholder="provider_user_id" value={providerUserId} onChange={(e) => setProviderUserId(e.target.value)} />
        <input className="w-full border rounded p-2 mb-2" placeholder="access_token" value={accessToken} onChange={(e) => setAccessToken(e.target.value)} />
        <button className="w-full border rounded p-2 bg-green-600 text-white" onClick={onOAuthLogin} disabled={loading}>
          OAuthログイン
        </button>
      </section>

      {error && <p className="text-red-500">{error}</p>}
    </main>
  );
}
