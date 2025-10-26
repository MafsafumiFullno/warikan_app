# warikan_app

割り勘アプリケーション（Laravel + Next.js）

## 概要

このプロジェクトは、Dockerを用いてLaravel + PostgreSQL バックエンドと、Next.js（React + TypeScript）フロントエンドによる開発環境を構築した割り勘アプリケーションです。

## 技術スタック

### バックエンド
- **フレームワーク**: Laravel 12
- **言語**: PHP 8.3
- **データベース**: PostgreSQL 15
- **認証**: Laravel Sanctum
- **ORM**: Eloquent ORM

### フロントエンド
- **フレームワーク**: Next.js 15
- **言語**: TypeScript
- **UIライブラリ**: React 19
- **スタイリング**: Tailwind CSS 4
- **状態管理**: React Context API

### インフラ・開発環境
- **コンテナ**: Docker, Docker Compose
- **バージョン管理**: Git
- **パッケージ管理**: Composer (PHP), npm (Node.js)

## アーキテクチャ概要

```
[フロントエンド (Next.js)] ←→ [バックエンド (Laravel)] ←→ [データベース (PostgreSQL)]
     ↓                              ↓
[React Components]              [API Controllers]
[TypeScript]                    [PHP]
[Tailwind CSS]                  [Laravel Sanctum]
```

### 主要機能
- **ユーザー認証**: ゲストログイン、ユーザー登録、ログイン
- **プロジェクト管理**: プロジェクトのCRUD操作
- **割り勘計算**: 複数の割り勘方法に対応した計算ロジック
- **精算機能**: プロジェクト完了時の精算処理

## プロジェクト構成

```
warikan_app/
├── backend/             # Laravelプロジェクト
├── frontend/            # Next.jsアプリケーション
├── docker-compose.yml   # Docker定義ファイル
├── docs/               # ドキュメント
└── README.md           # このファイル
```

## 環境構築手順

### 1. 前提条件

- Docker Desktop
- Node.js (フロントエンド開発用)

### 2. 初期セットアップ

#### Docker環境の起動

```bash
# Dockerコンテナを起動
docker compose up -d

# Laravelコンテナに入る
docker compose exec app bash
```

#### Laravel環境の設定

```bash
# .envファイルを作成（コンテナ内で実行）
cp .env.example .env

# アプリケーションキーを生成
php artisan key:generate

# 設定をクリア
php artisan config:clear
```

#### データベース設定

`.env`ファイルのDB設定を以下のように変更：

```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=warikan
DB_USERNAME=user
DB_PASSWORD=password
```

#### マイグレーション実行

```bash
php artisan migrate
```

### 3. 開発サーバーの起動

#### バックエンド（Laravel）

```bash
# Laravelコンテナ内で実行
php artisan serve --host=0.0.0.0
```

#### フロントエンド（Next.js）

```bash
# フロントエンドディレクトリで実行
cd frontend
npm run dev
```

### 4. アクセス

- **フロントエンド**: http://localhost:3000
- **バックエンドAPI**: http://localhost:8000
- **APIテストページ**: http://localhost:3000/api-test

### 5. 開発終了時

```bash
# Ctrl+C（Next.js / Laravel）
docker compose down
```

※ `docker compose down -v` を実行した場合、ボリュームが削除され、データベースの内容が失われます。再度マイグレーションが必要になります。

## API エンドポイント

### 認証関連
- `POST /api/auth/guest-login` - ゲストログイン
- `POST /api/auth/register` - ユーザー登録
- `POST /api/auth/login` - ログイン
- `POST /api/auth/logout` - ログアウト（認証必要）
- `GET /api/auth/me` - ユーザー情報取得（認証必要）

### プロジェクト関連（認証必要）
- `GET /api/projects` - プロジェクト一覧
- `POST /api/projects` - プロジェクト作成
- `GET /api/projects/{id}` - プロジェクト詳細
- `PUT /api/projects/{id}` - プロジェクト更新
- `DELETE /api/projects/{id}` - プロジェクト削除
- `POST /api/projects/{id}/settlement` - 精算実行

### その他
- `GET /api/example` - API疎通確認
- `GET /api/csrf-token` - CSRFトークン取得

## 詳細ドキュメント

より詳細な情報については、以下のドキュメントを参照してください：

- [詳細な環境構築手順](docs/割り勘アプリ_バックエンド環境構築.txt)
- [技術スタック・アーキテクチャ詳細](docs/割り勘アプリ_技術スタック_アーキテクチャ.txt)
- [データベース設計図](docs/ER-Diagram.drawio)

## 開発ワークフロー

### 開発開始時
```bash
# Docker起動
docker compose up -d

# Laravelコンテナに入る
docker compose exec app bash

# Laravel起動（別ターミナルでも可）
php artisan serve --host=0.0.0.0

# フロントエンド起動
cd frontend
npm run dev
```

### 開発終了時
```bash
# Ctrl+C（Next.js / Laravel）
docker compose down
```

## セキュリティ

- **認証**: Laravel Sanctum によるトークンベース認証
- **CSRF保護**: Laravel のCSRF保護機能
- **CORS設定**: API アクセス制御
- **入力検証**: Laravel バリデーション機能

## 今後の拡張予定
- **リアルタイム通信**: WebSocket によるリアルタイム更新（他ユーザーとの共有編集）
- **モバイル対応**: 検討中
- **決済機能**: 外部決済API連携
- **通知機能**: メール・プッシュ通知
