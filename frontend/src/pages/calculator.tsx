import { useState } from "react";
import AuthGuard from '@/components/AuthGuard';
import Layout from '@/components/Layout';

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
    <AuthGuard>
      <Layout title="電卓" showProjectsButton={false}>
        <div className="flex flex-col items-center justify-center min-h-full">
          <h2 className="text-xl font-bold mb-4">電卓</h2>
          <div className="w-64 bg-white p-4 rounded shadow">
            <div className="mb-4 text-right text-xl border p-2 rounded bg-gray-100 min-h-[2.5rem]">
              {input || "0"}
            </div>
            <div className="grid grid-cols-4 gap-2">
              {[7,8,9,"/"].map((v) => (
                <button key={v} className="py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors" onClick={() => handleClick(String(v))}>{v}</button>
              ))}
              {[4,5,6,"*"].map((v) => (
                <button key={v} className="py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors" onClick={() => handleClick(String(v))}>{v}</button>
              ))}
              {[1,2,3,"-"].map((v) => (
                <button key={v} className="py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors" onClick={() => handleClick(String(v))}>{v}</button>
              ))}
              <button className="py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors" onClick={handleClear}>C</button>
              <button className="py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors" onClick={() => handleClick("0")}>0</button>
              <button className="py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors" onClick={() => handleClick("+")}>+</button>
              <button className="py-2 bg-blue-400 text-white rounded hover:bg-blue-500 transition-colors" onClick={handleEqual}>=</button>
            </div>
          </div>
        </div>
      </Layout>
    </AuthGuard>
  );
}