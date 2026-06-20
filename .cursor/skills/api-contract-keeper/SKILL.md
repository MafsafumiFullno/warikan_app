---
name: api-contract-keeper
description: API契約の変更を frontend, backend, tests で同期し不整合を防ぐ。エンドポイント追加変更、レスポンス項目変更、ID仕様変更時に使う。
disable-model-invocation: true
---

# API Contract Keeper

## 目的

API の入出力契約を単一の真実として扱い、層間のズレを防ぐ。

## 使うタイミング

- エンドポイント追加・変更
- リクエスト/レスポンス項目の追加・削除・型変更
- 識別子ルール（例: `project_member_id`）の変更

## 同期フロー

1. 契約差分を定義する
   - path, method, request, response, error を明文化
2. Backend を同期する
   - route, controller, service, validation を更新
3. Frontend を同期する
   - API 呼び出し、型定義、UI 表示・エラーハンドリングを更新
4. Tests を同期する
   - Unit/Feature で契約差分を検証
   - 既存契約を壊していないことを確認

## チェックリスト

- [ ] path / method が一致
- [ ] request の必須項目・型・制約が一致
- [ ] response のキー名（snake_case 含む）が一致
- [ ] エラー時のステータス/形式が一致
- [ ] ID の意味が一致（`id` と `project_member_id` の混同なし）

## 出力テンプレート

```markdown
## 契約差分
- Endpoint:
- Request:
- Response:
- Error:

## 反映状況
- Backend:
- Frontend:
- Tests:

## 未反映リスク
- ...
```

## warikan_app 向け注意

- Frontend の API 呼び出しは `@/lib/api` の `apiFetch` を使う
- Laravel 側は既存の Service/Controller 分離責務に従う
