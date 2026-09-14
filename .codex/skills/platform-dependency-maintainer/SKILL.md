---
name: platform-dependency-maintainer
description: Platform Engineering観点で依存更新、PHP/Node警告、CI失敗、E2Eブラウザ準備を検知し、最小修正と検証を行ってPR準備まで進める。
disable-model-invocation: true
---

# Platform Dependency Maintainer

## 使いどころ

- Dependabot PR、依存更新、PHP / Node / GitHub Actions の互換性警告を扱う
- CI、Composer、npm、Playwright、GitHub Actions の失敗を切り分ける
- テストを通すために依存、ランナー、警告隔離、ブラウザ準備の設定を整える
- アプリ仕様ではなく、開発・検証基盤の健全性を保つ
- 本番信頼性や復旧性への影響がある場合はSREレビュー観点を残す

## 原則

- 自動マージはしない
- 依存更新とアプリ機能修正は原則として分ける
- 依存パッケージ内部は直接書き換えず、更新、設定、隔離、上流対応待ちで扱う
- アプリコード由来の警告は抑制せず、最小修正で消す
- 依存由来の一時的な警告隔離は、解除条件をPR本文かdocsに残す
- push、PR作成、force push、外部設定変更はユーザー承認後に実行する

## Observe

1. ブランチ、未追跡ファイル、既存差分を確認する
2. 失敗しているコマンド、CIジョブ、依存PRの対象を特定する
3. 失敗を次へ分類する
   - アプリコード由来の警告またはエラー
   - 依存パッケージ由来の警告
   - ランナー、OS、PHP、Node、ブラウザなど実行環境の問題
   - テスト仕様またはAPI契約の問題
4. `composer.json`、`package.json`、CI、Dependabot、Playwright設定を確認する

## Act

- アプリコード由来なら、最小修正で警告や失敗を解消する
- 依存由来なら、まず依存更新で解消できるか確認し、難しい場合はテスト時の一時隔離を検討する
- CIが失敗を見逃している場合は、`continue-on-error` の扱いと必須テストを見直す
- 複数ランタイムでの検知が必要なら、PHP / Node のmatrixを追加する
- Playwrightブラウザが原因なら、CIとローカルの入口でブラウザ準備が揃うようにする
- Dependabotが未設定なら、Composer、npm、GitHub Actions の更新PRを週次で作る
- 自動マージを入れる場合は、semver patch と開発依存の semver minor に限定する
- runtime 依存の semver minor と semver major は自動マージ対象外にする

## Verify

変更範囲に応じて、以下を実行または未実行理由を残す。

```text
cd backend && composer test
cd frontend && npm run lint
cd frontend && npm run build
cd frontend && npm run e2e
git diff --check
```

依存PRでは、少なくとも対象エコシステムのロックファイルとテスト結果を確認する。

## Learn

- 検知した失敗パターンと対応をPR本文またはdocsに残す
- 一時的な警告隔離には解除条件を残す
- CI時間、PR増加、ブラウザダウンロードなどの運用コストを明示する
- 次回から自動化できる作業と、承認が必要な作業を分ける

## 出力

- 原因分類
- 実施した最小修正
- 実行した検証コマンドと結果
- 残した一時対応と解除条件
- PR対象外にしたファイルや変更
- 承認待ちの操作
