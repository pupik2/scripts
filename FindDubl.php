<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
set_time_limit(0);

$tokenFile = __DIR__ . '/token_info.json';

if (!file_exists($tokenFile)) {
    die('Файл токена не найден: ' . htmlspecialchars($tokenFile, ENT_QUOTES));
}

$tokenData = json_decode(file_get_contents($tokenFile), true);

if (!is_array($tokenData) || empty($tokenData['accessToken']) || empty($tokenData['baseDomain'])) {
    die('Некорректные данные токена в ' . htmlspecialchars($tokenFile, ENT_QUOTES));
}

$accessToken = $tokenData['accessToken'];
$baseDomain  = $tokenData['baseDomain'];

function normilizePhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone ?? '');
    return $digits ?? '';
}

function normalizeEmail(string $email): string
{
    $email = trim((string)$email);
    return mb_strtolower($email, 'UTF-8');
}

function indexContacts(array $contacts, array &$phoneIndex, array &$emailIndex): void
{
    foreach ($contacts as $contact) {
        $id   = $contact['id']   ?? null;
        $name = $contact['name'] ?? '';

        if (!$id || empty($contact['custom_fields_values'])) {
            continue;
        }

        foreach ($contact['custom_fields_values'] as $field) {
            if (empty($field['field_code']) || empty($field['values'])) {
                continue;
            }

            // Телефоны
            if ($field['field_code'] === 'PHONE') {
                foreach ($field['values'] as $v) {
                    $raw = $v['value'] ?? '';
                    $phone = normilizePhone($raw);
                    if ($phone === '') {
                        continue;
                    }
                    $phoneIndex[$phone][] = [
                        'id'   => $id,
                        'name' => $name,
                    ];
                }
            }

            // Email
            if ($field['field_code'] === 'EMAIL') {
                foreach ($field['values'] as $v) {
                    $raw = $v['value'] ?? '';
                    $email = normalizeEmail($raw);
                    if ($email === '') {
                        continue;
                    }
                    $emailIndex[$email][] = [
                        'id'   => $id,
                        'name' => $name,
                    ];
                }
            }
        }
    }
}
function fetchContactsPage(string $baseDomain, string $accessToken, int $page = 1, int $limit = 100): array
{
    $baseUrl = "https://{$baseDomain}/api/v4/contacts";

    $params = [
        'limit'              => $limit,
        'page'               => $page,
        'filter[is_deleted]' => 0,
    ];

    $url = $baseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $maxRetries = 3;
    $attempt    = 0;

    while (true) {
        $attempt++;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$accessToken}",
                "Accept: application/json",
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $errno     = curl_errno($ch);
        curl_close($ch);

        if ($response === false || $errno) {
            error_log("fetchContactsPage: попытка {$attempt}, cURL ошибка: {$curlError}");

            if ($attempt >= $maxRetries) {
                die("Ошибка cURL (страница {$page}): {$curlError}");
            }

            sleep(2);
            continue;
        }

        if ($httpCode >= 500 && $httpCode < 600) {
            error_log("fetchContactsPage: попытка {$attempt}, HTTP {$httpCode}");

            if ($attempt >= $maxRetries) {
                die("Ошибка сервера (HTTP {$httpCode}), страница {$page}");
            }

            sleep(3);
            continue;
        }

        // Остальное — фатально
        if ($httpCode < 200 || $httpCode >= 300) {
            die("Ошибка при запросе HTTP {$httpCode}<br><pre>" .
                htmlspecialchars($response, ENT_QUOTES) . '</pre>');
        }

        break;
    }

    $data = json_decode($response, true);
    if (empty($data['_embedded']['contacts'])) {
        return [];
    }

    return $data['_embedded']['contacts'];
}

function collectDupl(array $index): array
{
    $result = [];
    foreach ($index as $key => $contacts) {
        if (count($contacts) < 2) {
            continue;
        }
        $result[] = [
            'key'      => $key,
            'contacts' => $contacts,
        ];
    }
    return $result;
}

$phoneIndex = [];
$emailIndex = [];

$page        = 1;
$totalLoaded = 0;

while (true) {
    $contacts = fetchContactsPage($baseDomain, $accessToken, $page, 100);
    $count = count($contacts);
    if ($count === 0) {
        break;
    }

    $totalLoaded += $count;
    indexContacts($contacts, $phoneIndex, $emailIndex);

    $page++;
}

$phoneDups = collectDupl($phoneIndex);
$emailDups = collectDupl($emailIndex);

$phoneGroupsCount = count($phoneDups);
$phoneDupCount    = 0;
foreach ($phoneDups as $g) {
    $phoneDupCount += count($g['contacts']);
}

$emailGroupsCount = count($emailDups);
$emailDupCount    = 0;
foreach ($emailDups as $g) {
    $emailDupCount += count($g['contacts']);
}

?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Дубли контактов amoCRM</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }
        h1, h2 {
            margin-bottom: 0.4em;
        }
        .summary {
            margin-bottom: 16px;
        }
        .section-empty {
            color: #777;
            margin-bottom: 20px;
        }
        .dup-list {
            margin: 10px 0 30px 0;
        }
        .dup-row {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            padding: 4px 6px;
            margin-bottom: 6px;
            background: #e3f2fd;
            border-radius: 4px;
            border: 1px solid #90caf9;
        }
        .dup-row-email {
            background: #fff3e0;
            border-color: #ffb74d;
        }
        .dup-count {
            display: inline-block;
            min-width: 20px;
            text-align: center;
            padding: 2px 6px;
            margin-right: 6px;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #90caf9;
            font-weight: bold;
            font-size: 12px;
        }
        .dup-key {
            font-weight: bold;
            margin-right: 8px;
            font-size: 13px;
        }
        .dup-tag {
            display: inline-block;
            padding: 2px 6px;
            margin: 2px 4px 2px 0;
            border-radius: 3px;
            background: #ffffff;
            border: 1px solid #90caf9;
            font-size: 12px;
        }
        .dup-row-email .dup-count,
        .dup-row-email .dup-tag {
            border-color: #ffb74d;
        }
        .dup-tag a {
            text-decoration: none;
            color: #1565c0;
        }
        .dup-x {
            color: #d32f2f;
            font-weight: bold;
            margin-left: 6px;
            cursor: default;
        }
    </style>
</head>
<body>
<h1>Дубликаты контактов amoCRM</h1>
<p>Аккаунт: <strong><?= htmlspecialchars($baseDomain, ENT_QUOTES) ?></strong></p>
<p>Всего обработано контактов: <strong><?= (int)$totalLoaded ?></strong></p>

<h2>По полю «Телефон»</h2>

<?php if ($phoneGroupsCount === 0): ?>
    <p class="section-empty">Дубликатов по телефону не найдено.</p>
<?php else: ?>
    <div class="summary">
        Найдено <strong><?= (int)$phoneDupCount ?></strong> дублей по полю «Телефон»<br>
        Найдено <strong><?= (int)$phoneGroupsCount ?></strong> групп по полю «Телефон»
    </div>

    <div class="dup-list">
        <?php foreach ($phoneDups as $group): ?>
            <?php $count = count($group['contacts']); ?>
            <div class="dup-row">
                <span class="dup-count"><?= $count ?></span>
                <span class="dup-key"><?= htmlspecialchars($group['key'], ENT_QUOTES) ?></span>
                <?php foreach ($group['contacts'] as $c): ?>
                    <?php
                    $id   = (int)$c['id'];
                    $name = (string)$c['name'];
                    $link = 'https://' . $baseDomain . '/contacts/detail/' . $id;
                    ?>
                    <span class="dup-tag">
                        <a href="<?= htmlspecialchars($link, ENT_QUOTES) ?>" target="_blank">#<?= $id ?></a>
                        <?= htmlspecialchars($name, ENT_QUOTES) ?>
                    </span>
                <?php endforeach; ?>
                <span class="dup-x">×</span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>По полю «Email»</h2>

<?php if ($emailGroupsCount === 0): ?>
    <p class="section-empty">Дубликатов по email не найдено.</p>
<?php else: ?>
    <div class="summary">
        Найдено <strong><?= (int)$emailDupCount ?></strong> дублей по полю «Email»<br>
        Найдено <strong><?= (int)$emailGroupsCount ?></strong> групп по полю «Email»
    </div>

    <div class="dup-list">
        <?php foreach ($emailDups as $group): ?>
            <?php $count = count($group['contacts']); ?>
            <div class="dup-row dup-row-email">
                <span class="dup-count"><?= $count ?></span>
                <span class="dup-key"><?= htmlspecialchars($group['key'], ENT_QUOTES) ?></span>
                <?php foreach ($group['contacts'] as $c): ?>
                    <?php
                    $id   = (int)$c['id'];
                    $name = (string)$c['name'];
                    $link = 'https://' . $baseDomain . '/contacts/detail/' . $id;
                    ?>
                    <span class="dup-tag">
                        <a href="<?= htmlspecialchars($link, ENT_QUOTES) ?>" target="_blank">#<?= $id ?></a>
                        <?= htmlspecialchars($name, ENT_QUOTES) ?>
                    </span>
                <?php endforeach; ?>
                <span class="dup-x">×</span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html>