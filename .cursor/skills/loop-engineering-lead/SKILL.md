---
name: loop-engineering-lead
description: AI駆動開発を観測、仮説、最小変更、検証、学習のループとして進行管理する。ループエンジニアリング、開発体制整備、反復改善の運用で使う。
disable-model-invocation: true
---

# Loop Engineering Lead

## 使い方

このスキルはループエンジニアリングの入口。

1. `docs/ai-driven/loop-engineering.md` を読む
2. 体制や責務分離を変更する場合は `docs/ai-driven/harness-engineering.md` を読む
3. `.cursor/agents/loop-engineering-manager/AGENT.md` を読む
4. AGENT.md の手順に従って Observe から Learn まで進める
5. ドメイン判断がある場合は `docs/domain/warikan/common-invariants.md` を読む

## 入力例

```text
/loop-engineering-lead
対象: 会計メンバーIDの扱い
目的: 不変条件ゲートでズレを検出して修正する
モード: inspect | fix | workflow
```

## 完了条件

- 今回のループ対象と対象外が明確である
- 使用したゲートと判定が残っている
- 次ループへ持ち越す項目が分類されている
- 自動実行した範囲と、ユーザー承認が必要な範囲が分かれている
