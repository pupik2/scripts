<?php
# 7a614c76-bb90-4da1-ae70-e20a121f7799 = id интеграции client_id
# xPt6Ow4SXRZV3ANs0CEVeF4LcXtDOdTJDnTp8EKvWNzf8Qotco9ZYbDFEdW1STYk = секретный ключ client_secret
# https://example.com = redirect_uri

define('TOKEN_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'token_info.json');

// включим вывод ошибок, чтобы видеть, если что-то пойдёт не так
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

error_reporting(E_ALL);

use AmoCRM\OAuth2\Client\Provider\AmoCRM;

require __DIR__ . '/vendor/autoload.php';

session_start();
/**
 * Создаем провайдера
 */
$provider = new AmoCRM([
    'clientId' => '7a614c76-bb90-4da1-ae70-e20a121f7799',
    'clientSecret' => 'xPt6Ow4SXRZV3ANs0CEVeF4LcXtDOdTJDnTp8EKvWNzf8Qotco9ZYbDFEdW1STYk',
    'redirectUri' => 'http://localhost:8000/index.php',
]);

if (isset($_GET['referer'])) {
    $provider->setBaseDomain($_GET['referer']);
}

if (!isset($_GET['request'])) {
    if (!isset($_GET['code'])) {
        /**
         * Просто отображаем кнопку авторизации или получаем ссылку для авторизации
         * По-умолчанию - отображаем кнопку
         */
        $_SESSION['oauth2state'] = bin2hex(random_bytes(16));
        if (true) {
            echo '<div>
                <script
                    class="amocrm_oauth"
                    charset="utf-8"
                    data-client-id="' . htmlspecialchars($provider->getClientId(), ENT_QUOTES) . '"
                    data-title="Установить интеграцию"
                    data-compact="false"
                    data-class-name="className"
                    data-color="default"
                    data-state="' . htmlspecialchars($_SESSION['oauth2state'], ENT_QUOTES) . '"
                    data-error-callback="handleOauthError"
                    src="https://www.amocrm.ru/auth/button.min.js"
                ></script>
                </div>';
            echo '<script>
            function handleOauthError(event) {
                alert("ID клиента - " + event.client_id + " Ошибка - " + event.error);
            }
            </script>';
            die;
        } else {
            $authorizationUrl = $provider->getAuthorizationUrl(['state' => $_SESSION['oauth2state']]);
            header('Location: ' . $authorizationUrl);
        }
    } elseif (
        empty($_GET['state']) ||
        empty($_SESSION['oauth2state']) ||
        ($_GET['state'] !== $_SESSION['oauth2state'])
    ) {
        unset($_SESSION['oauth2state']);
        exit('Invalid state');
    }

    /**
     * Ловим обратный код и меняем его на токен
     */
    try {
        /** @var \League\OAuth2\Client\Token\AccessToken $accessToken */
        $accessToken = $provider->getAccessToken(
            new League\OAuth2\Client\Grant\AuthorizationCode(),
            ['code' => $_GET['code']]
        );

        if (!$accessToken->hasExpired()) {
            saveToken([
                'accessToken'  => $accessToken->getToken(),
                'refreshToken' => $accessToken->getRefreshToken(),
                'expires'      => $accessToken->getExpires(),
                'baseDomain'   => $provider->getBaseDomain(),
            ]);
        }
    } catch (Exception $e) {
        die('Ошибка при получении токена: ' . (string)$e);
    }

    /** @var \AmoCRM\OAuth2\Client\Provider\AmoCRMResourceOwner $ownerDetails */
    $ownerDetails = $provider->getResourceOwner($accessToken);

    printf('Hello, %s!<br>', htmlspecialchars($ownerDetails->getName(), ENT_QUOTES));
    echo 'Токен сохранён в файл: ' . TOKEN_FILE;

} else {
    $accessToken = getToken();

    $provider->setBaseDomain($accessToken->getValues()['baseDomain']);

    /**
     * Проверяем активен ли токен и делаем запрос или обновляем токен
     */
    if ($accessToken->hasExpired()) {
        /**
         * Получаем токен по рефрешу
         */
        try {
            $accessToken = $provider->getAccessToken(
                new League\OAuth2\Client\Grant\RefreshToken(),
                ['refresh_token' => $accessToken->getRefreshToken()]
            );

            saveToken([
                'accessToken'  => $accessToken->getToken(),
                'refreshToken' => $accessToken->getRefreshToken(),
                'expires'      => $accessToken->getExpires(),
                'baseDomain'   => $provider->getBaseDomain(),
            ]);

        } catch (Exception $e) {
            die('Ошибка при обновлении токена: ' . (string)$e);
        }
    }

    $token = $accessToken->getToken();

    try {
        /**
         * Делаем тестовый запрос к АПИ
         */
        $data = $provider->getHttpClient()
            ->request('GET', $provider->urlAccount() . 'api/v2/account', [
                'headers' => $provider->getHeaders($accessToken)
            ]);

        $parsedBody = json_decode($data->getBody()->getContents(), true);
        printf('ID аккаунта - %s, название - %s', $parsedBody['id'], $parsedBody['name']);
    } catch (GuzzleHttp\Exception\GuzzleException $e) {
        var_dump((string)$e);
    }
}

/**
 * Сохраняем токен в TOKEN_FILE
 */
function saveToken($accessToken)
{
    if (
        isset($accessToken['accessToken']) &&
        isset($accessToken['refreshToken']) &&
        isset($accessToken['expires']) &&
        isset($accessToken['baseDomain'])
    ) {
        $data = [
            'accessToken'  => $accessToken['accessToken'],
            'expires'      => $accessToken['expires'],
            'refreshToken' => $accessToken['refreshToken'],
            'baseDomain'   => $accessToken['baseDomain'],
        ];

        $dir = dirname(TOKEN_FILE);
        if (!is_dir($dir)) {
            // создаём директорию, если её нет (на случай, если вынесешь файл в подкаталог)
            mkdir($dir, 0777, true);
        }

        $result = file_put_contents(
            TOKEN_FILE,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        if ($result === false) {
            exit('Не удалось записать файл токена: ' . TOKEN_FILE);
        }
    } else {
        exit('Invalid access token ' . var_export($accessToken, true));
    }
}

/**
 * @return \League\OAuth2\Client\Token\AccessToken
 */
function getToken()
{
    if (!file_exists(TOKEN_FILE)) {
        exit('Файл токена не найден: ' . TOKEN_FILE);
    }

    $accessToken = json_decode(file_get_contents(TOKEN_FILE), true);

    if (
        isset($accessToken['accessToken']) &&
        isset($accessToken['refreshToken']) &&
        isset($accessToken['expires']) &&
        isset($accessToken['baseDomain'])
    ) {
        return new \League\OAuth2\Client\Token\AccessToken([
            'access_token' => $accessToken['accessToken'],
            'refresh_token'=> $accessToken['refreshToken'],
            'expires'      => $accessToken['expires'],
            'baseDomain'   => $accessToken['baseDomain'],
        ]);
    } else {
        exit('Invalid access token structure: ' . var_export($accessToken, true));
    }
}