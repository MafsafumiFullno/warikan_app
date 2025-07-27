import { useState } from "react";
import Link from "next/link";

export default function Calculator() {
  const [input, setInput] = useState<string>("");

  const handleClick = (value: string) => {
    setInput((prev) => prev + value);
  };

  const handleClear = () => {
    setInput("");
  };

  const handleEqual = () => {
    try {
      // eslint-disable-next-line no-eval
      const result = eval(input);
      setInput(String(result));
    } catch {
      setInput("エラー");
    }
  };

  return (
    <main className="flex flex-col items-center justify-center min-h-screen">
      <Link href="/home" className="mb-4 self-start">
        <button className="px-4 py-2 border rounded bg-gray-100">ホーム画面に戻る</button>
      </Link>
      <h1 className="text-2xl font-bold mb-4">電卓</h1>
      <div className="w-64 bg-white p-4 rounded shadow">
        <div className="mb-4 text-right text-xl border p-2 rounded bg-gray-100 min-h-[2.5rem]">
          {input || "0"}
        </div>
        <div className="grid grid-cols-4 gap-2">
          {[7,8,9,"/"].map((v) => (
            <button key={v} className="py-2 bg-gray-200 rounded" onClick={() => handleClick(String(v))}>{v}</button>
          ))}
          {[4,5,6,"*"].map((v) => (
            <button key={v} className="py-2 bg-gray-200 rounded" onClick={() => handleClick(String(v))}>{v}</button>
          ))}
          {[1,2,3,"-"].map((v) => (
            <button key={v} className="py-2 bg-gray-200 rounded" onClick={() => handleClick(String(v))}>{v}</button>
          ))}
          <button className="py-2 bg-gray-200 rounded" onClick={handleClear}>C</button>
          <button className="py-2 bg-gray-200 rounded" onClick={() => handleClick("0")}>0</button>
          <button className="py-2 bg-gray-200 rounded" onClick={() => handleClick("+")}>+</button>
          <button className="py-2 bg-blue-400 text-white rounded" onClick={handleEqual}>=</button>
        </div>
      </div>
    </main>
  );
}