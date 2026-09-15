---
name: qa-spec-guard
description: 実装が仕様に一致しているかを検証し、テスト不足と回帰リスクを抽出する。PR前確認、受け入れ判定、品質ゲート運用で使う。
disable-model-invocation: true
---

# QA Spec Guard

## 目的

「実装した」ではなく「仕様を満たした」を判定する。

## 使うタイミング

- PR 作成前
- 受け入れテスト前
- 仕様変更後の回帰確認時

## レビュー観点

QA 判定は1人の総合判定ではなく、必要な観点を役割分割して確認する。各 Reviewer は自分の観点で Pass / Pass with Notes / Need Follow-up / Fail を出し、最終判定は最も厳しい判定に合わせる。

1. Spec Reviewer
   - 受け入れ基準の各項目に対応するテストがあるか
   - 正常系、異常系、境界値の抜けがないか
   - エラー時のメッセージ/ステータスが仕様どおりか
2. Contract Reviewer
   - API request / response / error / ID意味が backend, frontend, tests で同期しているか
   - TypeScript型とLaravel validationが矛盾していないか
   - Frontend の API 呼び出しが `@/lib/api` の `apiFetch` を使っているか
3. Data Reviewer
   - 永続化結果をテストで確認しているか
   - 論理削除・権限・識別子の扱いが一貫しているか
   - マイグレーション、既存データ、ロールバック方針の影響が整理されているか
4. Regression Reviewer
   - 変更箇所の周辺機能に回帰観点があるか
   - 主要ユーザーフローに影響がある変更で E2E が実行されているか
   - UI 変更、認証フロー変更、画面遷移変更時に E2E を必須とする
5. Security Reviewer
   - 認証、認可、入力検証、権限境界が仕様どおりか
   - 秘密情報、環境変数、共有リンク、削除済みデータが不要に露出しないか
   - 依存関係、ログ、エラーレスポンスに機微情報が含まれないか
6. Reliability Reviewer
   - production reliability、availability、performance、incident/recovery、capacity、resilience に影響する変更か
   - SLO / SLI や observability を新たに定義・変更する必要があるか
   - 現在の `warikan_app` 規模で必要なレビュー観点に絞り、独立したReliability SkillやAgentを前提にしない

## Reviewer 適用基準

- 小さな表示変更や文言修正: Spec Reviewer
- Backend Service / DB / 権限変更: Spec, Data, Regression Reviewer
- API変更やFrontend連携変更: Spec, Contract, Regression Reviewer
- 認証、認可、共有リンク、入力検証、秘密情報、依存関係に関わる変更: Security Reviewer
- `project_member_id` / `del_flg` に関わる変更: Spec, Contract, Data, Security, Regression Reviewer
- マイグレーションを伴う変更: Data Reviewer を必須にする
- 主要ユーザーフロー、認証、画面遷移に関わる変更: Regression Reviewer を必須にする
- Runtime、Docker、CI/CD、本番設定、デプロイ手順に影響する変更: Reliability Reviewer を必要に応じて含める
- 重いクエリ、N+1、集計処理、割り勘計算など性能劣化の可能性がある変更: Reliability Reviewer を必要に応じて含める
- SLO / SLI、observability、incident/recovery、capacity、resilience を明示的に扱う変更: Reliability Reviewer を必須にする

## Reliability Review Checklist

Reliability Review は専用Skillを新設せず、このSkill内の必要時レビューとして扱う。現時点の `warikan_app` では、AWS本番環境、Observability基盤、SLO/SLI運用、Incident対応が本格化するまでは、過剰なReliability設計を求めない。

確認する観点:

- Availability: 主要APIや画面導線が失敗時に過度に止まらないか
- Performance: N+1、不要な全件取得、重い集計、過剰なE2E/CI時間増加がないか
- Observability: 失敗時に原因を追える最低限のログやエラー情報があるか
- Incident / Recovery: デプロイ失敗、マイグレーション失敗、設定ミス時の戻し方が説明できるか
- Capacity: データ件数増加で明らかに破綻する処理を追加していないか
- Resilience: 外部サービス、DB、認証、共有リンク、Runtime設定の失敗がユーザー影響として整理されているか
- SLO / SLI: 現時点で新規定義が必要か。必要ない場合は「現時点では不要」と明記する

Reliability Review を適用しない例:

- 表示文言や静的UIの軽微な変更
- 単純なCRUDで性能、復旧、本番設定に影響しない変更
- テストやドキュメントのみで本番動作に影響しない変更

## E2E 実行基準

- 次のいずれかに該当する場合、E2E 実行を必須にする
  - フロントエンド画面・遷移の変更
  - ログイン/認証/権限系の変更
  - API 契約変更で UI 側の挙動に影響がある変更

## E2E 実行コマンド（warikan_app）

```bash
cd frontend && npm run e2e
```

## 判定ルール

- 必須テスト欠落: **Fail**
- 仕様と異なる挙動: **Fail**
- 振る舞い維持の根拠が不十分: **Need Follow-up**
- 軽微な改善余地のみ: **Pass with Notes**
- いずれかの Reviewer が Fail の場合、最終判定は **Fail**
- いずれかの Reviewer が Need Follow-up の場合、最終判定は少なくとも **Need Follow-up**

## 出力テンプレート

```markdown
## 判定
- 結果: Pass | Pass with Notes | Need Follow-up | Fail

## 根拠
- 受け入れ基準との対応:
- 確認したテスト:
- E2E 実行結果:

## Reviewer 判定
- Spec Reviewer:
- Contract Reviewer:
- Data Reviewer:
- Regression Reviewer:
- Security Reviewer:
- Reliability Reviewer:

## 検出事項
- Critical:
- Major:
- Minor:

## 追加で必要なテスト
- [ ] ...
```

## warikan_app 向け注意

- メンバー API は `project_member_id` 基準で確認する
- `del_flg` と権限（owner/member）の境界テストを必須観点に含める
