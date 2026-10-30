# APIエラー契約

`/api/*` の失敗レスポンスは、コントローラー・認証ミドルウェア・ルーティングを
通じて次のJSON形式を使用する。成功レスポンスとHTTPステータスの意味は維持する。

```json
{
  "message": "プロジェクトが見つかりません",
  "error": "NOT_FOUND",
  "errors": {}
}
```

| 例外・状況 | HTTP | error |
| --- | --- | --- |
| BadRequestException | 400 | BAD_REQUEST |
| 未認証 | 401 | UNAUTHENTICATED |
| ForbiddenException / 認可拒否 | 403 | FORBIDDEN |
| NotFoundException / モデル・ルート不在 | 404 | NOT_FOUND |
| 非対応メソッド | 405 | METHOD_NOT_ALLOWED |
| ConflictException / メンバー重複 | 409 | CONFLICT |
| CSRF不一致 | 419 | CSRF_TOKEN_MISMATCH |
| ValidationException | 422 | VALIDATION_ERROR |
| レート制限 | 429 | TOO_MANY_REQUESTS |
| 予期しない例外・内部設定不備 | 500 | INTERNAL_SERVER_ERROR |

`message` は表示用であり、プログラムの分岐にはHTTPステータスか固定の `error` を
使用する。`errors` は常にオブジェクトで、422ではフィールド名からメッセージ配列への
マップになる。既存フロントエンドの `apiFetch` は引き続き `message` を表示できる。
従来の自由文 `error` は固定コードに変更する。

サービスは明示的な型付き例外を投げる。文言に「見つかりません」などが含まれるだけで
HTTPステータスを変えない。割り勘方法の入力不備は `split_method_id` の422とする。
マスターロール欠落は利用者のリソース不在ではなく内部設定不備なので500となる。
既存の認証入力不備（会員化済み・空のプロフィール更新）は400を維持する。

内部エラーはdebug設定にかかわらず定型文を返し、例外の詳細はサーバーログへ記録する。
共通コントローラーはリクエスト本文をログに含めない。HTTP例外の `Retry-After` などの
ヘッダーは保持する。Webページ向けの例外レンダリングは変更しない。
