import Link from 'next/link';

export default function Home() {
  return (
    <main className="flex flex-col items-center justify-center min-h-screen">
      <h1 className="text-3xl font-bold mb-8">ホーム画面</h1>
      <div className="flex flex-col gap-4">
        <Link href="/calculator">
          <button className="px-4 py-2 border rounded">電卓機能</button>
        </Link>
        <Link href="/tasks">
          <button className="px-4 py-2 border rounded">タスク一覧</button>
        </Link>
        <Link href="/tasks/new">
          <button className="px-4 py-2 border rounded">タスク追加</button>
        </Link>
      </div>
    </main>
  );
}
