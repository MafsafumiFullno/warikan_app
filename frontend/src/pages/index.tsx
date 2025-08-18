import Link from 'next/link';

export default function Home() {
  return (
    <main className="flex flex-col items-center justify-center min-h-screen gap-4">
      <h1 className="text-2xl font-bold">割り勘アプリ</h1>
      <Link href="/login">
        <button className="px-4 py-2 border rounded">ログインへ</button>
      </Link>
      <Link href="/api-test">
        <button className="px-4 py-2 border rounded">APIテスト</button>
      </Link>
    </main>
  );
}
