# フロントエンド開発依存の互換性（2026-10-31）

PR #34は互換性のある範囲へ調整して取り込む。

- Playwrightは1.64.1、eslint-config-nextは実行時Next.jsと同じ16.4.2へ更新する。
- ESLintはサポート中の10系へ更新する。9系は2026-08-06にEOLとなった。
- TypeScriptは5.9.3を維持する。typescript-eslintの公式対応範囲は
  `>=4.8.4 <6.1.0` であり、提案されていた7系は未対応。
- Nodeの型は実行環境24系に合わせ、提案されていた26系は導入しない。
- Next.js 16のネイティブflat configを直接読み込む。FlatCompatは使わず、
  不要になった直接依存 `@eslint/eslintrc` を削除する。
- 新たに既定有効になったReact Compiler向けの `immutability` と
  `set-state-in-effect` は警告として可視化する。現在React Compilerは有効化しておらず、
  依存更新時に画面の状態管理を広範囲に変更しない。既存のHooksルールは維持する。
- 今後のfrontend-toolingグループはminor/patchのみとし、メジャー更新の互換性問題で
  他の安全な更新を止めない。メジャー更新は個別に確認する。

Next.js公式は16.4以降のESLint 10対応を明示している。一部の同梱プラグインでは
ESLint 10をpeerDependenciesにまだ記載していないため、通常のnpm install/ciで
peer警告が出ることも公式に記載されている。`--force` や `--legacy-peer-deps` は使用しない。

開発用依存にはminimatchのDoS勧告 GHSA-g3hv-75wg-m249 に由来するmoderate警告が残る。
修正版11系を古いAPIの利用箇所へ一律overrideせず、上流の互換修正を待つ。
`npm audit fix --force` が提案するNext.js設定の古いメジャーへの変更も適用しない。
これは開発依存の残件であり、監査結果を「すべての依存に脆弱性がない」と解釈しない。

公式根拠:

- [typescript-eslint対応バージョン](https://typescript-eslint.io/users/dependency-versions/)
- [Next.js ESLint設定とESLint 10対応](https://nextjs.org/docs/app/api-reference/config/eslint)
- [ESLintサポート期間](https://eslint.org/version-support/)
- [Dependabotグループの更新種別](https://github.blog/changelog/2023-08-17-grouped-version-updates-by-semantic-version-level-for-dependabot/)
- [minimatch勧告](https://github.com/advisories/GHSA-g3hv-75wg-m249)
