# warikan_app DDD Design Policy

## 目的

`warikan_app` では、割り勘に関わる業務ルール、メンバー識別子、論理削除、権限、計算ロジックの扱いを安定させるために、DDD（Domain-Driven Design）の考え方を採用する。

ただし、フルDDDを一括導入するのではなく、プロジェクト規模に合わせた軽量DDDとして運用する。

DDDを採用する理由は次の通り。

- ドメインルールをControllerや画面都合から分離し、仕様変更時の影響範囲を読みやすくする
- `project_member_id` とDB内部ID、`del_flg` の扱いなど、壊してはいけない不変条件を明確にする
- 割り勘計算、会計対象メンバー、共有リンク、権限判定など、業務上の意味を持つ処理をテストしやすくする
- AI駆動開発時に、実装前の設計判断とレビュー観点を揃える

DDDを主に適用する領域は、次のようなドメイン複雑性を持つ処理に限定する。

- 割り勘計算、比重、支払いフロー
- プロジェクトメンバー識別子と参加状態
- 会計タスクと対象メンバー
- 論理削除済みデータの扱い
- オーナー権限、参加者権限、共有リンク権限
- 複数の集約やテーブルにまたがる整合性

単純なCRUD、表示用の整形、明確なLaravel標準処理まで、機械的にEntity、Value Object、Repositoryへ分解しない。導入コストがドメイン理解や保守性を上回る場合は、既存のController / Service / Eloquent構成を優先する。

## Architecture

`warikan_app` の基本的な責務分離は次の通り。

```text
Presentation
    ↓
Application
    ↓
Domain

Infrastructure
    ↑ implements interfaces defined by Application / Domain
```

依存方向は原則として上から下へ向ける。Presentation は Application を呼び出し、Application は Domain を使う。Domain は Laravel、Eloquent、HTTP、DB、外部APIなどの具体実装へ直接依存しない。

Infrastructure は、Application / Domain が定義したInterfaceを実装する。DB永続化、Eloquent、外部API、フレームワーク固有機能はInfrastructureへ置く。

現時点の既存コードは、LaravelのServiceとEloquent Modelを中心にした構成である。既存コードを一括でこの層構造へ移すことはしない。今後の変更でドメイン複雑性が高い箇所から段階的に適用する。

### Presentation

Presentation層は、HTTP入出力を扱う。

主な責務:

- Controller
- Request validationの呼び出し
- Response / Resource への変換
- 認証済みユーザーやRoute parameterの受け取り
- Application層への処理委譲

Controllerは薄く保つ。Controllerにドメインルール、DBクエリ、権限境界の詳細、割り勘計算ロジックを書かない。Controllerは入力を受け取り、Use CaseまたはApplication Serviceを呼び、結果をHTTPレスポンスへ変換する。

### Application

Application層は、ユースケースを実行する。

主な責務:

- Use Case
- Application Service
- Transaction coordination
- DTO
- 認可済み操作の流れの制御
- Repository Interfaceを通じた永続化の呼び出し
- Domain Objectの生成、取得、保存の順序制御

Application層には、ドメインルールそのものを書かない。たとえば、割り勘計算の配分ルール、削除済みメンバーを有効メンバーとして扱わないルール、プロジェクト内メンバー識別子の意味などはDomain層へ置く。

Application層が扱ってよい判断は、次のような手続き上の判断に限る。

- どのUse Caseを実行するか
- どのRepositoryから何を取得するか
- どの順番でDomain Objectを操作するか
- トランザクション境界をどこに置くか
- DTOとして何を返すか

### Domain

Domain層は、割り勘アプリの業務ルールを表現する。

Domain層はLaravel / Eloquentへ直接依存しないことを原則とする。Domain ObjectはHTTP request、response、Eloquent Model、Query Builder、Controller、Laravel validationに依存しない。

ただし、既存コードではEloquent ModelがEntityと永続化を兼ねている箇所がある。これは現状許容し、今後ドメイン複雑性が高まった箇所から段階的に分離する。

### Infrastructure

Infrastructure層は、技術的な具体実装を扱う。

主な責務:

- Eloquent
- DB
- Repository Implementation
- External API
- Framework固有実装
- Laravel validationやconfigurationへの接続
- 暗号化、トークン生成、メール送信などの具体実装

InfrastructureはDomain / Applicationで定義されたInterfaceを実装する。Infrastructureの都合をDomainへ持ち込まない。

## Domain

### Entity

Entityは、同一性を持ち、状態が変化しても識別され続けるドメインオブジェクトである。

導入基準:

- IDにより同一性を判定する必要がある
- ライフサイクルがある
- 状態変更にドメインルールが伴う
- 他の概念から参照される

候補例:

- Project
- ProjectMember
- ProjectTask
- ProjectShareLink

単なる表示用データや、一時的な入力データはEntityにしない。

### Value Object

Value Objectは、IDではなく値そのもので等価性を判断するドメインオブジェクトである。

導入基準:

- 値の妥当性を型として閉じ込めたい
- 複数箇所で同じ値ルールが繰り返される
- 値の意味がプリミティブ型だけでは不明確
- 不正な値を作れないようにしたい

候補例:

- ProjectMemberId
- Money / Amount
- SplitWeight
- ShareToken

ただし、1箇所でしか使わない単純な文字列や数値を機械的にValue Object化しない。

### Aggregate

Aggregateは、一貫性を同時に守る必要があるEntityやValue Objectのまとまりである。

導入基準:

- 複数のEntityを同時に更新しないと不変条件が壊れる
- 外部から内部Entityを自由に変更されると整合性が崩れる
- トランザクション境界として扱いたい
- ユースケース上、ひとまとまりとして操作される

候補例:

- Project と ProjectMember
- ProjectTask と ProjectTaskMember
- SplitCalculationに必要なProjectMember / ProjectTask / target membersの読み取りモデル

単純な一覧取得や検索結果をAggregateにしない。

### Aggregate Root

Aggregate Rootは、Aggregate外部から参照・操作される入口である。

方針:

- Aggregate内部のEntityは、原則としてAggregate Root経由で変更する
- 不変条件はAggregate RootまたはDomain Serviceで守る
- 外部から内部Entityを直接更新して整合性を壊さない

候補例:

- Project
- ProjectTask

### Domain Service

Domain Serviceは、特定のEntityやValue Objectだけに自然に置けないドメインルールを表す。

導入基準:

- 複数EntityやAggregateをまたいだ計算・判定が必要
- ルールがApplication Serviceに置かれると業務知識が漏れる
- 状態を持たない純粋なドメイン処理として表せる

候補例:

- 割り勘計算
- 支払いフロー計算
- 対象メンバー配分

外部API呼び出し、DB保存、HTTPレスポンス生成はDomain Serviceへ置かない。

### Repository Interface

Repository Interfaceは、Domain / Applicationが永続化の詳細を知らずにAggregateを取得・保存するための境界である。

導入基準:

- Eloquent QueryがUse CaseやDomainルールの見通しを悪くしている
- 同じ取得条件が複数Use Caseで重複している
- 永続化方式を隠してテストしたい
- Aggregate単位で取得・保存したい

導入しない基準:

- 単純なCRUDだけである
- Eloquentの標準的な取得で十分読みやすい
- Interfaceと実装を増やすだけでドメイン理解が深まらない

Repository InterfaceはApplicationまたはDomain側に置き、Eloquentを使った実装はInfrastructure側に置く。

### Domain Invariant

Domain Invariantは、どの実装経路でも守るべきドメイン不変条件である。

`warikan_app` では、`docs/domain/warikan/common-invariants.md` をドメイン不変条件の正本として扱う。

特に次の既存規約を壊してはいけない。

- API上のプロジェクトメンバー識別子は `project_member_id` を使う
- `project_members.id` はDB内部のPKであり、API識別子として扱わない
- ルートの `{memberId}` は文脈上 `project_member_id` を指す
- 論理削除は `del_flg` で表現する
- 取得・参照・更新・削除では、必要に応じて `del_flg = false` を前提にする
- FrontendのAPI呼び出しは `@/lib/api` の `apiFetch` を使う

新しい不変条件を見つけた場合は、まず `docs/domain/warikan/common-invariants.md` に追加し、必要に応じてRules / Skills / Testsへ反映する。

## Application

### Use Case

Use Caseは、利用者が達成したい目的を1つの単位として表す。

例:

- プロジェクトを作成する
- プロジェクトにメンバーを追加する
- メンバーの比重を更新する
- 会計を追加する
- 割り勘計算を実行する
- 共有リンクを作成する

Use Caseは、入力、出力、権限、トランザクション境界、呼び出すDomain処理を明確にする。

### Application Service

Application Serviceは、Use Caseの実行手順を担当する。

責務:

- 入力DTOを受け取る
- 必要なRepositoryを呼び出す
- Domain Objectへ処理を委譲する
- トランザクションを制御する
- 出力DTOを返す

Application Serviceに、割り勘配分、識別子の意味、論理削除の業務ルールなどを直接書かない。Application Serviceにルールが増えた場合は、Entity、Value Object、Domain Serviceへ移す候補とする。

### Transaction coordination

トランザクションはApplication層で制御する。

方針:

- 複数Aggregateまたは複数Repository更新を1つのUse Caseで扱う場合は、Application Serviceがトランザクション境界を持つ
- Domain Objectはトランザクション開始・commit・rollbackを知らない
- InfrastructureはDB接続やEloquent実装を担当し、Use Caseの一貫性境界はApplicationが決める

### DTO

DTOは層間の入力・出力を明確にするために使う。

導入基準:

- 配列のキーや型が複雑になっている
- Controller / Service / Testsで同じ構造が繰り返される
- API request / responseとDomain Objectを分けたい
- 型としてUse Caseの入力・出力を固定したい

単純な既存処理では、無理にDTOを増やさない。

## Infrastructure

Infrastructure層は、技術詳細を閉じ込める。

### Eloquent

Eloquent ModelはDB永続化の実装として扱う。既存コードではEntity的にも使われているが、今後ドメイン複雑性が高い箇所では、Domain ObjectとEloquent Modelを分けることを検討する。

Eloquent固有のQuery Builder、Relation、Scope、CastをDomain層へ持ち込まない。

### DB

DBのテーブル構造、マイグレーション、インデックス、外部キー、論理削除カラムはInfrastructureの詳細である。

ただし、DBに保存されている値がドメイン不変条件に関わる場合は、Domain Docsにも意味を記録する。

### Repository Implementation

Repository Implementationは、Repository InterfaceをEloquentやDBで実装する。

方針:

- Query BuilderやEloquentの詳細をApplication / Domainへ漏らさない
- Aggregate取得に必要なRelationや条件を実装側へ閉じ込める
- `del_flg = false` などの取得条件が不変条件に関わる場合は、Repository実装で一貫して扱う

### External API

外部API連携はInfrastructureに置く。Domain層は外部APIのSDK、HTTP client、レスポンス形式に依存しない。

### Framework固有実装

Laravel固有のvalidation、config、Crypt、Str、Logger、Queue、Mail、NotificationなどはInfrastructureまたはPresentation/Applicationの境界で扱う。Domain層へ直接持ち込まない。

## Presentation

### Controller

Controllerは薄く保つ。

Controllerの責務:

- HTTP requestを受け取る
- 認証ユーザーやRoute parameterを取得する
- Request / DTOへ入力を渡す
- Application Serviceを呼び出す
- Response / Resourceを返す

Controllerに書かないもの:

- ドメインルール
- Eloquent Query
- トランザクション制御
- 割り勘計算
- 複雑な権限境界
- レスポンス都合を超えたデータ加工

### Request

RequestはHTTP入力の検証を担当する。Request validationは、形式、必須項目、型、最大長など、入力境界の検証に集中する。

ドメイン上の意味や不変条件は、DomainまたはApplicationで検証する。たとえば、`project_member_id` がプロジェクト内で有効なメンバーを指すかどうかは、単なるHTTP validationだけで完結させない。

### Response / Resource

Response / ResourceはHTTP出力形式を担当する。

方針:

- Domain ObjectやEloquent Modelを無加工で外部公開しない
- API契約に必要な項目だけを返す
- DB内部IDとAPI識別子を混同しない
- `project_member_id` など、API上の意味を持つ識別子を明示する

## warikan_app 固有方針

`docs/domain/warikan/common-invariants.md` をドメイン不変条件の正本として扱う。

既存規約を壊さない。

- `project_member_id` はAPI上のプロジェクトメンバー識別子である
- `project_members.id` はDB内部PKであり、API識別子ではない
- `del_flg` は論理削除を表す
- 削除済みメンバーや削除済み会計を有効データとして扱わない
- API request / response / frontend state / backend testsで識別子の意味を揃える

割り勘計算、会計対象メンバー、共有リンク公開情報は、削除済みデータや権限境界の影響を受けやすい。変更時は `common-invariants.md` と関連テストを確認する。

## 適用判断

新しいEntity、Value Object、Repository、Domain Serviceを機械的に増やさない。

導入を検討する条件:

- ドメインルールが複数箇所に重複している
- 配列やプリミティブ型だけでは意味を取り違えやすい
- `project_member_id` と内部IDのように、識別子の混同がバグにつながる
- 論理削除、権限、計算、状態遷移が絡む
- ServiceがDB操作、手順制御、ドメイン判断を抱え込みすぎている
- テストでドメインルールだけを切り出して検証したい

導入を見送る条件:

- 単純なCRUDである
- Laravel / Eloquentの標準構成で十分読みやすい
- 1箇所だけの処理で抽象化による利益が少ない
- Interfaceと実装を増やすだけで、変更容易性や安全性が上がらない
- 現在のチームやAI駆動開発フローにとって理解コストが高い

既存コードの大規模リファクタリングは今回は行わない。今後の変更から、次の順序で段階的に適用する。

1. 変更対象のドメイン不変条件を確認する
2. 既存Serviceで安全に変更できる場合は、最小変更で対応する
3. ルールが重複または肥大化する場合だけ、Domain ObjectやDomain Serviceを切り出す
4. 永続化条件が複雑化した場合だけ、Repository Interface / Implementationを導入する
5. 新しい判断基準や不変条件は、実装と同時にDocsとTestsへ反映する

## Evolutionary DDD（段階的進化）

`warikan_app` のDDDは、最初から完成形を固定しない。軽量DDDから始め、変更ごとにシステムの現在地とドメイン複雑性を確認しながら、必要になった構造だけを少しずつ導入する。

現状の Laravel Service + Eloquent 構成は否定しない。既存Serviceで安全に表現できる処理は、そのまま最小変更で扱う。

DDD構造への進化を検討する兆候:

- ドメインルールが複数のService、Controller、Frontendに散らばっている
- 同じ判定や変換が繰り返されている
- `project_member_id` / `del_flg` などの不変条件が壊れやすい
- 配列やプリミティブ型だけでは意味を取り違えやすい
- テストがDBやEloquentに密結合し、ドメインルールだけを検証しづらい
- 変更のたびに複数箇所の整合性確認が必要になる
- 集計、割り勘計算、権限、論理削除、状態遷移が絡んでServiceが肥大化している

進化の進め方:

1. 変更ごとにドメイン複雑性を評価する
2. 既存構造で十分なら「DDD構造追加不要」と明記する
3. 兆候がある場合は、Entity / Value Object / Aggregate / Repository / Domain Service のどれが必要かを提案する
4. 大規模リファクタを勝手に実施せず、提案、issue化、小さなPRから進める
5. 導入した判断は、設計記録とテストに残す

設計記録の置き場:

- ドメイン不変条件: `docs/domain/warikan/common-invariants.md`
- DDD方針: `docs/architecture/ddd.md`
- 個別の設計判断や未決事項: `docs/issues/` または今後追加する `docs/domain/warikan/` 配下の設計メモ

AIエージェントの責務:

- Engineering Manager は、DDD進化が必要な兆候を検知し、責務分配、handoff、過剰設計抑制を行う。ただし、ドメイン最終判断は Product Owner と Product / Specification に戻す
- Spec Architect は、変更ごとに DDD Design Check を行い、構造追加が必要か不要かを判断する
- TDD Implementer は、実装中にDDD判断が未確定だと分かった場合、独自判断で構造を増やさず `spec-architect` へ戻す
- Platform Engineering は、DDD判断やApplication設計判断を確定しない
