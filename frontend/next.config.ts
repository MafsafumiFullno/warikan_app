import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  async rewrites() {
    // 開発環境と本番環境で異なるバックエンドURLを使用
    const backendUrl = process.env.NODE_ENV === 'production' 
      ? process.env.BACKEND_URL || 'http://backend:8000'
      : process.env.BACKEND_URL || 'http://localhost:8000';
    
    return [
      {
        source: '/api/:path*',
        destination: `${backendUrl}/api/:path*`,
      },
    ];
  },
};

export default nextConfig;
