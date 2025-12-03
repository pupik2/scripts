<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$tokenFile = __DIR__ . '/token_info.json';

if (!file_exists($tokenFile)) {
    die("Нету файла: {$tokenFile}" . PHP_EOL);
}

$tokenData = json_decode(file_get_contents($tokenFile), true);
if (!is_array($tokenData) || empty($tokenData['accessToken']) || empty($tokenData['baseDomain'])) {
    die("Нету данных {$tokenFile}" . PHP_EOL);
}

if (!empty($tokenData['expires']) && time() >= $tokenData['expires']) {
    die("access_token истёк" . PHP_EOL);
}

$accessToken = $tokenData['accessToken'];
$baseDomain  = $tokenData['baseDomain'];

$totalContacts = 100000;
$batchSize     = 250;
$poolSize      = 10000;

$minIntervalSeconds = 0.15;
$lastRequestTs = 0.0;

$apiUrl = "https://{$baseDomain}/api/v4/contacts";
//создание телефона
$phonesPool = [];
for ($i = 0; $i < $poolSize; $i++) {
    $phonesPool[] = sprintf('+7000%07d', $i);
}
//создание mail
$emailsPool = [];
for ($i = 0; $i < $poolSize; $i++) {
    $emailsPool[] = "asp{$i}@example.ru";
}

function makeContact(int $index, array $phonesPool, array $emailsPool): array
{
    $phoneKeys = array_rand($phonesPool, 2);
    $emailKeys = array_rand($emailsPool, 2);

    $phone1 = $phonesPool[$phoneKeys[0]];
    $phone2 = $phonesPool[$phoneKeys[1]];
    $email1 = $emailsPool[$emailKeys[0]];
    $email2 = $emailsPool[$emailKeys[1]];

    return [
        'name' => "Контакт № {$index}",
        'custom_fields_values' => [
            [
                'field_code' => 'PHONE',
                'values' => [
                    ['value' => $phone1, 'enum_code' => 'MOB'],
                    ['value' => $phone2, 'enum_code' => 'WORK'],
                ],
            ],
            [
                'field_code' => 'EMAIL',
                'values' => [
                    ['value' => $email1, 'enum_code' => 'WORK'],
                    ['value' => $email2, 'enum_code' => 'PRIV'],
                ],
            ],
        ],
    ];
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json",
        "Accept: application/json",
    ],
]);

$created = 0;

while ($created < $totalContacts) {
    $toCreateNow = min($batchSize, $totalContacts - $created);
    $batch = [];

    for ($i = 0; $i < $toCreateNow; $i++) {
        $contactIndex = $created + $i + 1;
        $batch[] = makeContact($contactIndex, $phonesPool, $emailsPool);
    }

    $now = microtime(true);
    $elapsed = $now - $lastRequestTs;
    if ($elapsed < $minIntervalSeconds) {
        $sleepSeconds = $minIntervalSeconds - $elapsed;
        usleep((int)round($sleepSeconds * 1_000_000));
    }

    $payload = json_encode($batch, JSON_UNESCAPED_UNICODE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $maxRetries = 3;
    $attempt    = 0;

    while (true) {
        $attempt++;

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            echo "Ошибка cURL (попытка {$attempt}): {$error}" . PHP_EOL;

            if ($attempt >= $maxRetries) {
                echo "Превышено число попыток, выхожу." . PHP_EOL;
                exit(1);
            }

            sleep(2);
            continue;
        }

        if ($httpCode >= 500 && $httpCode < 600) {
            echo "Серверная ошибка {$httpCode} (попытка {$attempt})." . PHP_EOL;
            echo "Ответ сервера:" . PHP_EOL . $response . PHP_EOL;

            if ($attempt >= $maxRetries) {
                echo "Превышено число попыток, выхожу." . PHP_EOL;
                exit(1);
            }

            sleep(3);
            continue;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            echo "Ошибка при создании контактов. HTTP код: {$httpCode}" . PHP_EOL;
            echo "Ответ сервера:" . PHP_EOL . $response . PHP_EOL;
            exit(1);
        }

        break;
    }

    $created += $toCreateNow;
    echo "Создано контактов: {$created}/{$totalContacts}" . PHP_EOL;
}

curl_close($ch);
echo "Готово." . PHP_EOL;