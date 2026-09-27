# Top Page Final Revision

参照：Master v5.5、Decision Log v4.3、AI Coding Rules、Top Page Final Implementation、現在のトップと `/jobs/start`、今回依頼文。
添付名「トップページ修正依頼(1).pdf」の実ファイルは添付領域・docsで見つからず、PDF画像との照合は未実施。依頼文に転記された赤字7項目を最優先で反映した。

Scope：Hero・ピクトグラム・指定CTAのVisual Revision、共有入力フォーム、既存CSRF初期化のトップへの適用、関連テスト。
Do Not Change：DB schema / migration、Fit / Score / Ranking、検索・Compare、UserQuery保存契約、Published boundary / Snapshot / Review、Company Authoring、Mail、Provenance、Fact、応募経路、Crawler / Importer、Auth、Production config。
完了条件：赤字7項目、既存POST契約、3画面幅、全テスト、DB前後照合。commit / Gate 2 / deployは行わない。

## A. Verdict

**PASS（依頼文に転記された赤字7項目に対する実装・検証）。** 全864テスト、3画面幅、共有POST契約、DB不変を確認。赤字PDF実体との照合は未実施（O参照）。

## B. Red Mark Review

| 赤字指示 | 対応 |
|---|---|
| 1. Hero文字・イラストを大きく | PCの最大幅を1248→1440px、1440px見出し36→43.92px、イラスト幅約510→650px |
| 2. Hero CTA矢印を消す | 主CTA・企業向け副CTAの矢印を削除。文字・href・階層を維持 |
| 3. 重ねUIカードを消す | HTMLと専用CSSを除去。既存イラスト単独 |
| 4. 6ピクトグラムを大きく | 共通Componentのlarge表示。tile 40→64px、SVG 20→32px（各1.6倍） |
| 5. 淡いGreen Noteを消す | トップの「選ぶのは、あなた。」Noteと専用CSSを除去 |
| 6. その位置にかんたん入力 | Comparison直後に既存フォームを共有Partialで配置 |
| 7. 企業CTA矢印を消す | 末尾矢印だけ削除。導線・認証振り分けは維持 |

## C. Changed Files

今回の開始時点からの増分。前回の未コミット変更を含むGit差分全体とは区別する。

| File | 役割 |
|---|---|
| `resources/views/public/home.blade.php` | Overlay / Note削除、3CTA矢印削除、共通フォーム配置、6アイコンlarge指定 |
| `resources/css/app.css` | Hero拡大、largeアイコン、コンパクトフォーム、不要CSS除去 |
| `resources/views/components/pictogram-feature.blade.php` | largeオプション。3つの見方は従来サイズ |
| `resources/views/query/partials/entry-form.blade.php` | 新規共有フォーム。全field / option / help / error / CSRF / submit |
| `resources/views/query/partials/tool-selector.blade.php` | compact PC用の4列オプション。既存利用は3列維持 |
| `resources/views/query/start.blade.php` | 既存フォームを同じPartialの通常表示に置換 |
| `app/Http/Middleware/JobDecisionSession.php` | 既存フォーム用CSRF bootstrapの対象にhomeを追加（2箇所） |
| `tests/Feature/LandingPageTest.php` | 7項目、共有field / option / POST契約の検査 |
| `tests/Feature/JobSearchEntryTest.php` | 既存CSRF・session保護検査をhomeにも適用、画面間のtoken継続 |
| `docs/JobDD_Top_Page_Final_Revision.md` | 本報告 |

Header / Footer、カルーセルJS、求人取得Controllerは今回の増分では変更なし。

## D. Hero

コピー・Leadは維持。PCは横幅を広げ、左右を1.1:1に調整。1440pxの見出し43.92px、画像幅649.53px。1200pxは36.6px / 535.25px。390pxは27px / 358pxを維持。既存 `jobdd-hero-kinki.png` を利用し、追加画像・説明カードなし。

## E. CTA Arrow Removal

トップの「希望条件を入力する」「企業の方はこちら」「企業向けJobDDを見る」の3箇所のみ。新着カルーセル操作・求人詳細等の矢印には影響しない。

## F. Pictogram

6項目に同じlargeオプションを付け、共通CSSで1.6倍へ。PC3列×2段、390px2列。3つの見方は既存の40px tile / 20px SVGで、背景・余白とサイズの差を保つ。個別アイコンの補正なし。

## G. Compact Search Form

`query.partials.entry-form`をトップと `/jobs/start` で共用。field名、職種2種・近畿6府県・7ツール、validation markup、help、CSRF、submitを二重定義しない。選択肢の出所は既存Controller / Service定数。

PCは職種・地域・年収の1段＋ツールの1段。ツール選択肢は4列×2段。Mobileは縦積み。既存`custom_tools`も共有のまま残し、トップだけ「その他のCAD・ツールを入力（任意）」に折りたたむ。`/jobs/start`は通常表示を維持。

POST先は既存 `POST /jobs/start` (`jobs.store`)。validation、成功時のUserQuery保存・session保存・tools付き一覧遷移、失敗時の422入力再表示は既存処理のまま。hidden originや新しい保存状態を追加しない。

トップGETは従来CSRF bootstrap cookieを発行していなかったため、フォームを貼るだけでは初訪問POSTが419になる。既存 `JobDecisionSession` のフォーム対象にhomeを追加し、同じ暗号化HttpOnly / SameSite cookie・期限・照合条件をそのまま再利用した。新しいcookie・CSRF検証の緩和・認証や所有権の変更なし。GETはDB書込・session GCなし。トップと入力画面の間でもtokenが継続することを実CSRF有効テストで確認。

## H. Carousel

最大10件、既存公開境界・新着順、自動送り、hover/focus停止、スワイプ、キーボード、reduced motionを維持。JS・取得Controller・Job Card変更なし。Companyは14pxの補助色、Titleは18px・最大3行、CTAは下端で揃える既存の表示を維持。

## I. Company CTA

文言・淡青band・button hierarchy・既存企業入口を維持。末尾矢印だけ削除。Auth / route / role分岐変更なし。

## J. Common Components

`pictogram-feature`のlarge表示、既存Tool Selectorの列数指定、共有Entry Formを使用。既存blue / pale blue / white / radius / border / buttonを継承。新規依存・CMS・大きなsection Component・追加JSなし。

## K. PC / Mobile

通常ローカル8081のトップと `/jobs/start` をChromiumで1440 / 1200 / 390px確認。ページ全体横overflow・console error・pageerrorなし。PCナビ横並び、Mobile Menu・Escape復帰、フォームの操作、6アイコン、Before/After、準備中情報、企業CTA、Footerを確認。

フォームのfield / option / validation属性はFeature Testで同一性を照合。ブラウザでは選択・数値入力・任意自由記述の展開・入力・submitを実操作し、生成されたPOSTをブラウザ内で204応答して捕捉。既存POST先、occupation / region / salary_min / tools[] / custom_tools / _tokenと、HttpOnly bootstrap cookieを確認した。実業務DBへPOSTは送っていない。

Carouselは3画面幅で自動送り、focus停止、停止／再開、境界のループ、10件のTab移動、Arrow / Home、reduced motion時の停止と手動操作を確認。PC hover停止と390pxのtouch swipeも確認。コピーのfocus侵入なし。

390pxのツール名が途中で折れないよう、トップ内選択肢の横余白とgapだけ8pxに調整。`/jobs/start`のサイズ・選択肢表示は維持。

スクリプト：`/tmp/jobdd-top-revision/browser.py`、結果：`browser-results.json` / `browser.log`。検証ツールは/tmpの既存専用環境を再利用。

## L. Tests

開始baselineは前回実測 **854 PASS / 5,784 assertions**（`/tmp/jobdd-top-full-final.log`）。今回は前回の未コミット実装を継続して修正。

関連：**91 PASS / 778 assertions**。Landing / PublicNavigation / JobSearchEntry / JobUiV5。フォームoption・field属性・POST先一致、CSRF付き新規送信、GET書込なし、POSTは既存query＋sessionのみ、cookie改ざん・期限切れ・foreign・wrong token拒否、既存sessionの保護を含む。

| 最終検証 | 結果 |
|---|---|
| Full suite（Seeker / Company / Admin / Compare / 公開境界を含む） | **864 PASS / 5,841 assertions / 289.15秒** |
| Pint（今回変更PHP 3ファイル） | PASS |
| npm run build | PASS |
| git diff --check | PASS |
| Chromium 1440 / 1200 / 390px | PASS |

証跡：`/tmp/jobdd-top-revision/related.log`、`full.log`、`build.log`、`browser.log`、`browser-results.json`。全テストは既存Laravelコンテナ内の隔離testing DBで実行。ビルドの既存optional fontaine未導入案内は継続するが成功。アプリ依存・lockfile変更なし。

## M. DB Safety

ローカル`jobdd_v4`の全29テーブルで件数・全列SHA-256を前後照合し、**すべて一致**。companies / job_postings / job_facts / snapshots / user_queries / routes / interaction_logs等の業務データ変更なし。sessionsは3→3件、cache / cache_locksは0→0件。session/cacheを含め、今回観測された書込みなし。

通常8081ではGET表示とフォームのブラウザ操作のみ。POSTはサーバー到達前に捕捉し、実保存・遷移検証は隔離testing DBに限定した。schema変更・本番接続・実メール送信なし。

証跡：`/tmp/jobdd-top-revision/db-before.json` / `db-after.json`。前回報告のsession増加は今回baselineの3件に含まれ、今回は増加していない。

## N. Screenshots

保存先：`/tmp/jobdd-top-revision/`。

- `home-{1440,1200,390}.png`：全体
- `home-title-{1440,1200,390}.png`：Hero
- `features-title-{1440,1200,390}.png`：6ピクトグラム
- `preview-title-{1440,1200,390}.png`：Before / After
- `form-{1440,1200,390}.png`：かんたん入力
- `new-jobs-title-{1440,1200,390}.png`：Carousel
- `resources-title-{1440,1200,390}.png`：準備中3項目
- `company-cta-title-{1440,1200,390}.png`：企業CTA・Footer
- `menu-390.png`：Mobile Menu
- `start-{1440,1200,390}.png`：共有化後の `/jobs/start`

## O. Remaining Risks

- 赤字PDF実ファイルは未提供のため、依頼文に転記された7項目への適合を確認。PDF独自の追加指定との照合は未実施。
- Chromiumで確認。実機Safari・スクリーンリーダーでの全端末監査ではない。
- 通常ローカルDBへフォームPOSTは送らない。ブラウザでの送信内容と、隔離testing DBでの実CSRF・保存・遷移を分けて検証。

## P. Git

開始時はcleanではなく、前回の未コミット差分13ファイルあり。`/tmp/jobdd-top-revision/before/`へ控えを保存し、今回の土台として維持。commit / tag / push未実施。Gate 2 / Production deploy / 本番設定操作 / メール送信なし。とおる確認で停止。
