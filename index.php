<?php
declare(strict_types=1);

$dataPath = __DIR__ . '/data/pricing.json';
$error = null;
$meta = [];
$models = [];

if (!is_file($dataPath)) {
    $error = '料金設定ファイル (data/pricing.json) が見つかりません。';
} else {
    $raw = file_get_contents($dataPath);
    if ($raw === false) {
        $error = '料金設定ファイルの読み込みに失敗しました。';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $error = '料金設定ファイルの形式が正しくありません: ' . json_last_error_msg();
        } else {
            $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
            $models = is_array($decoded['models'] ?? null) ? $decoded['models'] : [];
        }
    }
}

$usdToJpy = isset($meta['usd_to_jpy']) ? (float) $meta['usd_to_jpy'] : 150.0;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatCurrency(float $value, string $currency): string
{
    $abs = abs($value);
    if ($currency === 'JPY') {
        $decimals = $abs >= 1 ? 0 : ($abs >= 0.1 ? 2 : 4);
        return '¥' . number_format($value, $decimals);
    }

    if ($abs >= 1) {
        $decimals = 2;
    } elseif ($abs >= 0.1) {
        $decimals = 3;
    } elseif ($abs >= 0.01) {
        $decimals = 4;
    } elseif ($abs >= 0.001) {
        $decimals = 5;
    } else {
        $decimals = 6;
    }

    return '$' . number_format($value, $decimals);
}

function formatNumber(float $value): string
{
    if (abs($value - round($value)) < 1e-9) {
        return number_format((int) round($value));
    }

    if (abs($value) >= 100) {
        return number_format($value, 1);
    }

    return number_format($value, 2);
}

$selectedModelId = (string) ($_GET['model'] ?? ($models[0]['id'] ?? ''));
$selectedModel = null;
foreach ($models as $model) {
    if (($model['id'] ?? '') === $selectedModelId) {
        $selectedModel = $model;
        break;
    }
}

if ($selectedModel === null && !empty($models)) {
    $selectedModel = $models[0];
    $selectedModelId = $selectedModel['id'] ?? '';
}

$usageInput = [];
if (isset($_GET['usage']) && is_array($_GET['usage'])) {
    $usageInput = $_GET['usage'];
}

$requestCountInput = $_GET['request_count'] ?? '';
$requestCount = 1.0;
if ($requestCountInput !== '') {
    $requestCount = filter_var($requestCountInput, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
    $requestCount = is_numeric($requestCount) ? (float) $requestCount : 1.0;
}

if ($requestCount < 0) {
    $requestCount = 0.0;
}

$requests = $requestCount;
$componentResults = [];
$totalUsd = 0.0;
if (is_array($selectedModel)) {
    $pricing = is_array($selectedModel['pricing'] ?? null) ? $selectedModel['pricing'] : [];
    foreach ($pricing as $component) {
        $componentId = (string) ($component['id'] ?? '');
        if ($componentId === '') {
            continue;
        }

        $rawValue = $usageInput[$componentId] ?? '';
        $usageValue = 0.0;
        if ($rawValue !== '') {
            $sanitized = filter_var(
                $rawValue,
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND
            );
            $usageValue = is_numeric($sanitized) ? (float) $sanitized : 0.0;
        }

        $unitSize = isset($component['unit_size']) && (float) $component['unit_size'] > 0
            ? (float) $component['unit_size']
            : 1.0;
        $pricePerUnitUsd = isset($component['price_per_unit_usd'])
            ? (float) $component['price_per_unit_usd']
            : 0.0;

        $priceUnitLabel = (string) ($component['unit'] ?? '単位');
        $inputUnitLabel = (string) ($component['input_unit'] ?? $priceUnitLabel);

        $unitCountPerRequest = $unitSize > 0 ? $usageValue / $unitSize : 0.0;
        $totalUnits = $unitCountPerRequest * $requests;
        $totalUsage = $usageValue * $requests;
        $costUsd = $totalUnits * $pricePerUnitUsd;
        $costJpy = $costUsd * $usdToJpy;

        $componentResults[] = [
            'id' => $componentId,
            'label' => (string) ($component['label'] ?? $componentId),
            'price_unit' => $priceUnitLabel,
            'input_unit' => $inputUnitLabel,
            'usage_per_request' => $usageValue,
            'total_usage' => $totalUsage,
            'requests' => $requests,
            'total_units' => $totalUnits,
            'cost_usd' => $costUsd,
            'cost_jpy' => $costJpy,
            'price_per_unit_usd' => $pricePerUnitUsd,
            'price_per_unit_jpy' => $pricePerUnitUsd * $usdToJpy,
            'help' => (string) ($component['help'] ?? ''),
            'optional' => (bool) ($component['optional'] ?? false),
        ];

        $totalUsd += $costUsd;
    }
}

$totalJpy = $totalUsd * $usdToJpy;
$perRequestUsd = ($requests > 0) ? $totalUsd / $requests : 0.0;
$perRequestJpy = $perRequestUsd * $usdToJpy;
$hasCost = $totalUsd > 0;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenAI API 料金計算ツール</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<main>
    <header>
        <h1>OpenAI API 料金計算ツール</h1>
        <p>
            OpenAI が提供する各種モデルの料金を日本円で試算できます。<br>
            モデルごとの単価は <code>data/pricing.json</code> で管理されており、最新の価格に合わせて自由に編集できます。
        </p>
    </header>

    <?php if ($error !== null): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="layout">
        <section class="card">
            <form method="get">
                <div class="field">
                    <label for="model">モデル</label>
                    <select name="model" id="model" onchange="this.form.submit()">
                        <?php foreach ($models as $model): ?>
                            <?php $id = (string) ($model['id'] ?? ''); ?>
                            <option value="<?= h($id) ?>" <?= $id === $selectedModelId ? 'selected' : '' ?>>
                                <?= h((string) ($model['name'] ?? $id)) ?>
                                <?php if (!empty($model['category'])): ?>
                                    (<?= h((string) $model['category']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="request_count">リクエスト数</label>
                    <input type="number" min="0" step="1" inputmode="numeric" name="request_count" id="request_count"
                           value="<?= h((string) $requests) ?>" placeholder="例: 10">
                    <p class="small-text">同じリクエストを繰り返す回数です。合計トークン数を直接入力したい場合は 1 のままにしてください。</p>
                </div>

                <?php if ($selectedModel): ?>
                    <div class="field">
                        <h3>利用量 (1 リクエストあたり)</h3>
                        <?php foreach ($selectedModel['pricing'] as $component): ?>
                            <?php
                            $componentId = (string) ($component['id'] ?? '');
                            $value = $usageInput[$componentId] ?? '';
                            ?>
                            <div class="component-field">
                                <label for="usage_<?= h($componentId) ?>">
                                    <?= h((string) ($component['label'] ?? $componentId)) ?>
                                    <?php if (!empty($component['optional'])): ?>
                                        <span class="badge-inline">任意</span>
                                    <?php endif; ?>
                                </label>
                                <div class="input-row">
                                    <input type="number" min="0" step="0.01" inputmode="decimal"
                                           id="usage_<?= h($componentId) ?>"
                                           name="usage[<?= h($componentId) ?>]"
                                           value="<?= h((string) $value) ?>"
                                           placeholder="0">
                                    <span><?= h((string) ($component['input_unit'] ?? ($component['unit'] ?? '単位'))) ?></span>
                                </div>
                                <?php if (!empty($component['help'])): ?>
                                    <p class="note"><?= h((string) $component['help']) ?></p>
                                <?php endif; ?>
                                <p class="note small-text">単価: <?= formatCurrency((float) ($component['price_per_unit_usd'] ?? 0.0), 'USD') ?> / <?= h((string) ($component['unit'] ?? '')) ?>（約 <?= formatCurrency(((float) ($component['price_per_unit_usd'] ?? 0.0)) * $usdToJpy, 'JPY') ?>）</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="buttons">
                    <button type="submit" name="calculate" value="1" class="primary">計算する</button>
                    <button type="reset" class="secondary" onclick="window.location='<?= h(basename(__FILE__)) ?>'">リセット</button>
                </div>
            </form>
        </section>

        <section class="card results">
            <h2>計算結果</h2>
            <?php if ($selectedModel): ?>
                <p class="small-text">
                    モデル: <strong><?= h((string) ($selectedModel['name'] ?? $selectedModelId)) ?></strong>
                    <?php if (!empty($selectedModel['description'])): ?>
                        — <?= h((string) $selectedModel['description']) ?>
                    <?php endif; ?>
                </p>
                <div class="summary">
                    <div class="badge">
                        <strong><?= formatCurrency($totalJpy, 'JPY') ?></strong>
                        <span>合計 (日本円)</span>
                    </div>
                    <div class="badge">
                        <strong><?= formatCurrency($totalUsd, 'USD') ?></strong>
                        <span>合計 (米ドル)</span>
                    </div>
                    <div class="badge">
                        <strong><?= formatCurrency($perRequestJpy, 'JPY') ?></strong>
                        <span>1 リクエストあたり (約 <?= formatCurrency($perRequestUsd, 'USD') ?>)</span>
                    </div>
                </div>

                <table>
                    <thead>
                    <tr>
                        <th>項目</th>
                        <th>利用量</th>
                        <th>金額 (USD)</th>
                        <th>金額 (JPY)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($componentResults as $component): ?>
                        <tr>
                            <td><?= h($component['label']) ?></td>
                            <td>
                                <?= formatNumber($component['usage_per_request']) ?> <?= h($component['input_unit']) ?>
                                × <?= formatNumber($component['requests']) ?> 回
                                <?php if ($component['requests'] > 1): ?>
                                    = <?= formatNumber($component['total_usage']) ?> <?= h($component['input_unit']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= formatCurrency($component['cost_usd'], 'USD') ?></td>
                            <td><?= formatCurrency($component['cost_jpy'], 'JPY') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total">
                    <span>合計</span>
                    <span><?= formatCurrency($totalJpy, 'JPY') ?>（<?= formatCurrency($totalUsd, 'USD') ?>）</span>
                </div>

                <?php if (!$hasCost): ?>
                    <div class="alert">利用量を入力すると料金を試算できます。初期表示では 0 円です。</div>
                <?php endif; ?>
            <?php else: ?>
                <p>モデル情報が読み込めませんでした。JSON ファイルを確認してください。</p>
            <?php endif; ?>

            <div class="disclaimer">
                <p>為替レート: 1 USD = <?= formatCurrency($usdToJpy, 'JPY') ?> （設定日: <?= h((string) ($meta['last_updated'] ?? '未設定')) ?>）</p>
                <?php if (!empty($meta['exchange_rate_source'])): ?>
                    <p>レート情報: <?= h((string) $meta['exchange_rate_source']) ?></p>
                <?php endif; ?>
                <?php if (!empty($meta['notes'])): ?>
                    <p><?= nl2br(h((string) $meta['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="card model-list">
        <h2>モデル別 単価一覧</h2>
        <table>
            <thead>
            <tr>
                <th>モデル</th>
                <th>項目</th>
                <th>単価 (USD)</th>
                <th>単価 (JPY)</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($models as $model): ?>
                <?php
                $isSelected = (($model['id'] ?? '') === $selectedModelId);
                $pricing = is_array($model['pricing'] ?? null) ? $model['pricing'] : [];
                $rowspan = max(count($pricing), 1);
                $rowClass = $isSelected ? 'highlight' : '';
                ?>

                <?php if (empty($pricing)): ?>
                    <tr class="<?= $rowClass ?>">
                        <td rowspan="<?= $rowspan ?>">
                            <?= h((string) ($model['name'] ?? $model['id'])) ?>
                            <?php if (!empty($model['category'])): ?>
                                <span class="badge-inline"><?= h((string) $model['category']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td colspan="3">価格情報が設定されていません</td>
                    </tr>
                <?php else: ?>
                    <?php $firstRow = true; ?>
                    <?php foreach ($pricing as $component): ?>
                        <tr class="<?= $rowClass ?>">
                            <?php if ($firstRow): ?>
                                <td rowspan="<?= $rowspan ?>">
                                    <?= h((string) ($model['name'] ?? $model['id'])) ?>
                                    <?php if (!empty($model['category'])): ?>
                                        <span class="badge-inline"><?= h((string) $model['category']) ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td><?= h((string) ($component['label'] ?? $component['id'])) ?></td>
                            <td><?= formatCurrency((float) ($component['price_per_unit_usd'] ?? 0.0), 'USD') ?> / <?= h((string) ($component['unit'] ?? '')) ?></td>
                            <td><?= formatCurrency(((float) ($component['price_per_unit_usd'] ?? 0.0)) * $usdToJpy, 'JPY') ?> / <?= h((string) ($component['unit'] ?? '')) ?></td>
                        </tr>
                        <?php $firstRow = false; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="disclaimer small-text">価格や為替レートは参考値です。実際の請求額は OpenAI の利用明細をご確認ください。</p>
    </section>

    <footer>
        <p>© <?= date('Y') ?> OpenAI API 料金計算ツール — JSON を編集して最新の価格を反映できます。</p>
    </footer>
</main>
</body>
</html>
