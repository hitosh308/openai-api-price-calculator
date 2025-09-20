<?php
declare(strict_types=1);

session_start();

const SESSION_KEY = 'admin_authenticated';
const DEFAULT_ADMIN_PASSWORD = 'price-edit';

$adminPasswordEnv = getenv('ADMIN_PASSWORD');
$adminPasswordEnv = is_string($adminPasswordEnv) ? trim($adminPasswordEnv) : '';
$adminPassword = $adminPasswordEnv !== '' ? $adminPasswordEnv : DEFAULT_ADMIN_PASSWORD;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function defaultPricingData(): array
{
    return [
        'meta' => [
            'usd_to_jpy' => 150.0,
            'last_updated' => '',
            'exchange_rate_source' => '',
            'notes' => '',
        ],
        'models' => [],
    ];
}

function formatFloatInput(float $value): string
{
    $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}

function parseFloatInput(mixed $value, float $default = 0.0): float
{
    if (is_string($value) || is_numeric($value)) {
        $filtered = filter_var(
            (string) $value,
            FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND
        );

        if (is_numeric($filtered)) {
            return (float) $filtered;
        }
    }

    return $default;
}

function renderComponentFields(string $modelKey, string $componentKey, array $component, bool $forTemplate = false): string
{
    $idValue = $forTemplate ? '' : (string) ($component['id'] ?? '');
    $labelValue = $forTemplate ? '' : (string) ($component['label'] ?? '');
    $unitValue = $forTemplate ? '' : (string) ($component['unit'] ?? '');
    $inputUnitValue = $forTemplate ? '' : (string) ($component['input_unit'] ?? '');
    $unitSizeRaw = $forTemplate ? null : ($component['unit_size'] ?? 1.0);
    $priceRaw = $forTemplate ? null : ($component['price_per_unit_usd'] ?? 0.0);
    $helpValue = $forTemplate ? '' : (string) ($component['help'] ?? '');
    $optionalChecked = (!$forTemplate && !empty($component['optional'])) ? 'checked' : '';

    $unitSizeValue = $forTemplate ? '' : formatFloatInput((float) $unitSizeRaw);
    $priceValue = $forTemplate ? '' : formatFloatInput((float) $priceRaw);

    ob_start();
    ?>
    <div class="component-card" data-component data-component-index="<?= h($componentKey) ?>">
        <div class="component-header">
            <h4>料金項目</h4>
            <button type="button" class="link-button" data-remove-component>削除</button>
        </div>
        <div class="grid two-column">
            <div class="field">
                <label>項目 ID</label>
                <input type="text"
                       name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][id]"
                       value="<?= h($idValue) ?>"
                       placeholder="例: prompt_tokens">
                <p class="small-text">JSON 内で利用する ID です。</p>
            </div>
            <div class="field">
                <label>表示名</label>
                <input type="text"
                       name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][label]"
                       value="<?= h($labelValue) ?>"
                       placeholder="例: プロンプト (入力トークン)">
            </div>
        </div>
        <div class="grid three-column">
            <div class="field">
                <label>単価 (USD)</label>
                <input type="number"
                       min="0"
                       step="0.000001"
                       name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][price_per_unit_usd]"
                       value="<?= h($priceValue) ?>"
                       placeholder="例: 0.002">
            </div>
            <div class="field">
                <label>単価の単位</label>
                <input type="text"
                       name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][unit]"
                       value="<?= h($unitValue) ?>"
                       placeholder="例: 1,000 トークン">
            </div>
            <div class="field">
                <label>入力単位</label>
                <input type="text"
                       name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][input_unit]"
                       value="<?= h($inputUnitValue) ?>"
                       placeholder="例: トークン">
            </div>
        </div>
        <div class="grid two-column">
            <div class="field">
                <label>単位あたりの個数</label>
                <input type="number"
                       min="0"
                       step="0.000001"
                       name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][unit_size]"
                       value="<?= h($unitSizeValue) ?>"
                       placeholder="例: 1000000">
                <p class="small-text">単価の 1 単位に相当する入力単位の数です。</p>
            </div>
            <div class="field checkbox-field">
                <label>
                    <input type="checkbox"
                           name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][optional]"
                           value="1" <?= $optionalChecked ?>>
                    任意項目 (利用量入力を省略可能)
                </label>
            </div>
        </div>
        <div class="field">
            <label>補足説明</label>
            <textarea name="models[<?= h($modelKey) ?>][pricing][<?= h($componentKey) ?>][help]"
                      rows="3"
                      placeholder="フォームの入力者向けに補足を表示できます。"><?= h($helpValue) ?></textarea>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function renderModelCard(string $modelKey, array $model, bool $forTemplate = false): string
{
    $idValue = $forTemplate ? '' : (string) ($model['id'] ?? '');
    $nameValue = $forTemplate ? '' : (string) ($model['name'] ?? '');
    $categoryValue = $forTemplate ? '' : (string) ($model['category'] ?? '');
    $descriptionValue = $forTemplate ? '' : (string) ($model['description'] ?? '');

    $pricing = is_array($model['pricing'] ?? null) ? array_values($model['pricing']) : [];
    if ($forTemplate && empty($pricing)) {
        $pricing = [[]];
    }

    $componentsHtml = '';
    foreach ($pricing as $componentIndex => $component) {
        $componentData = is_array($component) ? $component : [];
        $componentsHtml .= renderComponentFields($modelKey, (string) $componentIndex, $componentData, $forTemplate);
    }

    $nextComponentIndex = (string) count($pricing);

    ob_start();
    ?>
    <article class="model-card" data-model-card data-model-index="<?= h($modelKey) ?>">
        <div class="model-card-header">
            <h3>モデル設定</h3>
            <button type="button" class="link-button" data-remove-model>モデルを削除</button>
        </div>
        <div class="grid two-column">
            <div class="field">
                <label>モデル ID</label>
                <input type="text"
                       name="models[<?= h($modelKey) ?>][id]"
                       value="<?= h($idValue) ?>"
                       placeholder="例: gpt-4o">
                <p class="small-text">API で指定するモデル ID を入力してください。</p>
            </div>
            <div class="field">
                <label>表示名</label>
                <input type="text"
                       name="models[<?= h($modelKey) ?>][name]"
                       value="<?= h($nameValue) ?>"
                       placeholder="例: GPT-4o">
            </div>
        </div>
        <div class="grid two-column">
            <div class="field">
                <label>カテゴリ</label>
                <input type="text"
                       name="models[<?= h($modelKey) ?>][category]"
                       value="<?= h($categoryValue) ?>"
                       placeholder="例: フラッグシップ">
            </div>
            <div class="field full-width">
                <label>説明</label>
                <textarea name="models[<?= h($modelKey) ?>][description]"
                          rows="3"
                          placeholder="モデルの概要や用途を記載できます。"><?= h($descriptionValue) ?></textarea>
            </div>
        </div>
        <div class="component-list" data-component-container data-next-component-index="<?= h($nextComponentIndex) ?>">
            <?= $componentsHtml ?>
        </div>
        <button type="button" class="secondary add-component-button" data-add-component>+ 料金項目を追加</button>
    </article>
    <?php
    return (string) ob_get_clean();
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ' . basename(__FILE__) . '?logged_out=1');
    exit;
}

$dataPath = __DIR__ . '/data/pricing.json';
$success = null;
$error = null;
$loadWarning = null;

if (isset($_GET['logged_out'])) {
    $success = 'ログアウトしました。';
}

if (isset($_SESSION['admin_notice'])) {
    $notice = (string) $_SESSION['admin_notice'];
    unset($_SESSION['admin_notice']);
    $success = $success === null ? $notice : ($success . "\n" . $notice);
}

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = trim((string) ($_POST['password'] ?? ''));
    if ($password === '') {
        $error = 'パスワードを入力してください。';
    } elseif (!hash_equals((string) $adminPassword, $password)) {
        $error = 'パスワードが正しくありません。';
    } else {
        session_regenerate_id(true);
        $_SESSION[SESSION_KEY] = true;
        $_SESSION['admin_notice'] = 'ログインしました。';
        header('Location: ' . basename(__FILE__));
        exit;
    }
}

$authenticated = !empty($_SESSION[SESSION_KEY] ?? false);

if (!$authenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>料金設定管理 - ログイン</title>
        <link rel="stylesheet" href="assets/styles.css">
    </head>
    <body class="admin">
    <main class="narrow">
        <section class="card login-card">
            <h1>料金設定管理</h1>
            <p class="small-text">料金テーブルを編集するにはパスワードによる認証が必要です。</p>
            <?php if ($success !== null): ?>
                <div class="message success"><?= nl2br(h($success)) ?></div>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <div class="message error"><?= nl2br(h($error)) ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="field">
                    <label for="password">パスワード</label>
                    <input type="password" name="password" id="password" autocomplete="current-password" autofocus required>
                </div>
                <div class="buttons">
                    <button type="submit" class="primary">ログイン</button>
                    <a href="index.php" class="link-button">← 計算ツールへ戻る</a>
                </div>
            </form>
            <p class="small-text">パスワードは環境変数 <code>ADMIN_PASSWORD</code> で変更できます。</p>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$pricingData = defaultPricingData();

if (is_file($dataPath)) {
    $raw = file_get_contents($dataPath);
    if ($raw === false) {
        $loadWarning = '料金設定ファイルの読み込みに失敗しました。保存すると新しいファイルを作成します。';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $loadWarning = '料金設定ファイルの形式が正しくありません: ' . json_last_error_msg();
        } else {
            if (isset($decoded['meta']) && is_array($decoded['meta'])) {
                $pricingData['meta'] = array_merge($pricingData['meta'], $decoded['meta']);
            }
            if (isset($decoded['models']) && is_array($decoded['models'])) {
                $pricingData['models'] = array_values($decoded['models']);
            }
        }
    }
} else {
    $loadWarning = '料金設定ファイルが見つかりませんでした。保存すると新しく作成されます。';
}

if (isset($_POST['action']) && $_POST['action'] === 'save') {
    $newData = defaultPricingData();
    $metaInput = isset($_POST['meta']) && is_array($_POST['meta']) ? $_POST['meta'] : [];
    $usdToJpy = parseFloatInput($metaInput['usd_to_jpy'] ?? null, 0.0);
    $lastUpdated = trim((string) ($metaInput['last_updated'] ?? ''));
    $exchangeRateSource = trim((string) ($metaInput['exchange_rate_source'] ?? ''));
    $notes = trim((string) ($metaInput['notes'] ?? ''));

    $newData['meta']['usd_to_jpy'] = $usdToJpy;
    if ($lastUpdated !== '') {
        $newData['meta']['last_updated'] = $lastUpdated;
    }
    if ($exchangeRateSource !== '') {
        $newData['meta']['exchange_rate_source'] = $exchangeRateSource;
    }
    if ($notes !== '') {
        $newData['meta']['notes'] = $notes;
    }

    $validationErrors = [];
    if ($usdToJpy <= 0.0) {
        $validationErrors[] = '為替レート (USD→JPY) には 0 より大きい数値を入力してください。';
    }

    $modelsInput = isset($_POST['models']) && is_array($_POST['models']) ? array_values($_POST['models']) : [];
    foreach ($modelsInput as $modelIndex => $modelInput) {
        if (!is_array($modelInput)) {
            continue;
        }

        $modelId = trim((string) ($modelInput['id'] ?? ''));
        $modelName = trim((string) ($modelInput['name'] ?? ''));
        $modelCategory = trim((string) ($modelInput['category'] ?? ''));
        $modelDescription = trim((string) ($modelInput['description'] ?? ''));

        $modelData = [];
        $modelData['id'] = $modelId;
        if ($modelName !== '') {
            $modelData['name'] = $modelName;
        }
        if ($modelCategory !== '') {
            $modelData['category'] = $modelCategory;
        }
        if ($modelDescription !== '') {
            $modelData['description'] = $modelDescription;
        }

        if ($modelId === '') {
            $validationErrors[] = sprintf('モデル #%d の「モデル ID」を入力してください。', $modelIndex + 1);
        }

        $pricingInput = isset($modelInput['pricing']) && is_array($modelInput['pricing'])
            ? array_values($modelInput['pricing'])
            : [];

        $components = [];
        foreach ($pricingInput as $componentIndex => $componentInput) {
            if (!is_array($componentInput)) {
                continue;
            }

            $componentId = trim((string) ($componentInput['id'] ?? ''));
            $label = trim((string) ($componentInput['label'] ?? ''));
            $unit = trim((string) ($componentInput['unit'] ?? ''));
            $inputUnit = trim((string) ($componentInput['input_unit'] ?? ''));
            $price = parseFloatInput($componentInput['price_per_unit_usd'] ?? null, 0.0);
            $unitSize = parseFloatInput($componentInput['unit_size'] ?? null, 1.0);
            $help = trim((string) ($componentInput['help'] ?? ''));
            $optional = isset($componentInput['optional'])
                && (string) $componentInput['optional'] !== ''
                && $componentInput['optional'] !== '0';

            if ($componentId === '' && $label === '' && $unit === '' && $inputUnit === '' && $help === '' && $price === 0.0 && $unitSize === 1.0 && !$optional) {
                continue;
            }

            if ($componentId === '') {
                $validationErrors[] = sprintf(
                    'モデル「%s」の料金項目 #%d の「項目 ID」を入力してください。',
                    $modelId !== '' ? $modelId : '未設定',
                    $componentIndex + 1
                );
            }

            if ($unitSize <= 0.0) {
                $validationErrors[] = sprintf(
                    'モデル「%s」の料金項目「%s」の単位あたりの個数には 0 より大きい数値を入力してください。',
                    $modelId !== '' ? $modelId : '未設定',
                    $componentId !== '' ? $componentId : '#' . ($componentIndex + 1)
                );
                $unitSize = 1.0;
            }

            if ($price < 0.0) {
                $validationErrors[] = sprintf(
                    'モデル「%s」の料金項目「%s」の単価 (USD) には 0 以上の数値を入力してください。',
                    $modelId !== '' ? $modelId : '未設定',
                    $componentId !== '' ? $componentId : '#' . ($componentIndex + 1)
                );
                $price = 0.0;
            }

            $componentData = [
                'id' => $componentId,
                'price_per_unit_usd' => $price,
                'unit_size' => $unitSize,
            ];

            if ($label !== '') {
                $componentData['label'] = $label;
            }
            if ($unit !== '') {
                $componentData['unit'] = $unit;
            }
            if ($inputUnit !== '') {
                $componentData['input_unit'] = $inputUnit;
            }
            if ($help !== '') {
                $componentData['help'] = $help;
            }
            if ($optional) {
                $componentData['optional'] = true;
            }

            $components[] = $componentData;
        }

        $modelData['pricing'] = $components;
        $newData['models'][] = $modelData;
    }

    $pricingData = $newData;

    if (!empty($validationErrors)) {
        $error = implode("\n", $validationErrors);
    } else {
        $json = json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $error = '料金設定データのエンコードに失敗しました。入力内容を確認してください。';
        } else {
            $bytes = file_put_contents($dataPath, $json . PHP_EOL, LOCK_EX);
            if ($bytes === false) {
                $error = '料金設定ファイルの書き込みに失敗しました。ファイル権限を確認してください。';
            } else {
                $_SESSION['admin_notice'] = '料金設定を更新しました。';
                header('Location: ' . basename(__FILE__));
                exit;
            }
        }
    }
}

$meta = defaultPricingData()['meta'];
$meta = array_merge($meta, isset($pricingData['meta']) && is_array($pricingData['meta']) ? $pricingData['meta'] : []);
$usdToJpyValue = formatFloatInput((float) ($meta['usd_to_jpy'] ?? 0.0));
$lastUpdatedValue = (string) ($meta['last_updated'] ?? '');
$exchangeRateSourceValue = (string) ($meta['exchange_rate_source'] ?? '');
$notesValue = (string) ($meta['notes'] ?? '');
$models = isset($pricingData['models']) && is_array($pricingData['models']) ? array_values($pricingData['models']) : [];
$modelCount = count($models);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>料金設定管理</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="admin">
<main>
    <header class="admin-header">
        <h1>料金設定管理</h1>
        <p class="small-text">モデル別の単価や為替レートを編集し、計算ツールに即時反映できます。</p>
        <div class="admin-actions">
            <a href="index.php" class="link-button">← 計算ツールへ戻る</a>
            <a href="?logout=1" class="link-button">ログアウト</a>
        </div>
    </header>
    <?php if ($success !== null): ?>
        <div class="message success"><?= nl2br(h($success)) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div class="message error"><?= nl2br(h($error)) ?></div>
    <?php endif; ?>
    <?php if ($loadWarning !== null): ?>
        <div class="message notice"><?= nl2br(h($loadWarning)) ?></div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <input type="hidden" name="action" value="save">

        <section class="card admin-meta">
            <h2>為替レート・メタ情報</h2>
            <div class="grid two-column">
                <div class="field">
                    <label for="usd_to_jpy">USD → JPY 為替レート</label>
                    <input type="number" name="meta[usd_to_jpy]" id="usd_to_jpy" min="0" step="0.01" value="<?= h($usdToJpyValue) ?>">
                    <p class="small-text">合計金額の円換算に使用されます。</p>
                </div>
                <div class="field">
                    <label for="last_updated">設定日</label>
                    <input type="date" name="meta[last_updated]" id="last_updated" value="<?= h($lastUpdatedValue) ?>">
                </div>
                <div class="field">
                    <label for="exchange_rate_source">レート情報</label>
                    <input type="text" name="meta[exchange_rate_source]" id="exchange_rate_source" value="<?= h($exchangeRateSourceValue) ?>" placeholder="例: 市場想定レート (2025年3月上旬の平均値)">
                </div>
                <div class="field full-width">
                    <label for="notes">備考</label>
                    <textarea name="meta[notes]" id="notes" rows="3" placeholder="為替や料金についてのメモを入力できます。"><?= h($notesValue) ?></textarea>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>モデルごとの料金設定</h2>
            <p class="small-text">モデルは自由に追加・削除できます。料金項目が不要になった場合は「削除」をクリックしてください。</p>
            <div class="model-cards" data-models-container data-next-index="<?= h((string) $modelCount) ?>">
                <?php foreach ($models as $index => $model): ?>
                    <?= renderModelCard((string) $index, is_array($model) ? $model : []) ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="secondary add-component-button" data-add-model>+ モデルを追加</button>
        </section>

        <div class="buttons">
            <button type="submit" class="primary">保存する</button>
            <a href="index.php">キャンセル</a>
        </div>
    </form>
</main>

<script>
(function() {
    const modelsContainer = document.querySelector('[data-models-container]');
    if (!modelsContainer) {
        return;
    }

    let nextModelIndex = Number(modelsContainer.getAttribute('data-next-index') || '0');
    const modelTemplateEl = document.getElementById('model-template');
    const componentTemplateEl = document.getElementById('component-template');
    const modelTemplate = modelTemplateEl ? modelTemplateEl.innerHTML.trim() : '';
    const componentTemplate = componentTemplateEl ? componentTemplateEl.innerHTML.trim() : '';

    const addModelButton = document.querySelector('[data-add-model]');
    if (addModelButton) {
        addModelButton.addEventListener('click', () => {
            if (!modelTemplate) {
                return;
            }
            const currentIndex = nextModelIndex;
            const html = modelTemplate.replace(/__INDEX__/g, String(currentIndex));
            nextModelIndex += 1;
            modelsContainer.setAttribute('data-next-index', String(nextModelIndex));
            const fragment = document.createElement('template');
            fragment.innerHTML = html.trim();
            const card = fragment.content.firstElementChild;
            if (card) {
                card.setAttribute('data-model-index', String(currentIndex));
                modelsContainer.appendChild(card);
            }
        });
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-remove-model]')) {
            const card = target.closest('[data-model-card]');
            if (card) {
                card.remove();
            }
        }

        if (target.matches('[data-add-component]')) {
            const card = target.closest('[data-model-card]');
            if (!card || !componentTemplate) {
                return;
            }
            const modelIndex = card.getAttribute('data-model-index');
            if (!modelIndex) {
                return;
            }
            const container = card.querySelector('[data-component-container]');
            if (!container) {
                return;
            }
            const currentIndex = Number(container.getAttribute('data-next-component-index') || '0');
            const html = componentTemplate
                .replace(/__MODEL_INDEX__/g, modelIndex)
                .replace(/__COMPONENT_INDEX__/g, String(currentIndex));
            const fragment = document.createElement('template');
            fragment.innerHTML = html.trim();
            const component = fragment.content.firstElementChild;
            if (component) {
                component.setAttribute('data-component-index', String(currentIndex));
                container.appendChild(component);
                container.setAttribute('data-next-component-index', String(currentIndex + 1));
            }
        }

        if (target.matches('[data-remove-component]')) {
            const component = target.closest('[data-component]');
            if (component) {
                component.remove();
            }
        }
    });
})();
</script>

<script type="text/template" id="model-template">
<?= renderModelCard('__INDEX__', [], true) ?>
</script>
<script type="text/template" id="component-template">
<?= renderComponentFields('__MODEL_INDEX__', '__COMPONENT_INDEX__', [], true) ?>
</script>
</body>
</html>
