<?php
/**
 * Основные параметры WordPress.
 *
 * Скрипт для создания wp-config.php использует этот файл в процессе установки.
 * Необязательно использовать веб-интерфейс, можно скопировать файл в "wp-config.php"
 * и заполнить значения вручную.
 *
 * Этот файл содержит следующие параметры:
 *
 * * Настройки базы данных
 * * Секретные ключи
 * * Префикс таблиц базы данных
 * * ABSPATH
 *
 * @link https://ru.wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Параметры базы данных: Эту информацию можно получить у вашего хостинг-провайдера ** //
/** Имя базы данных для WordPress */
define( 'DB_NAME', 'wordpress_db' );

/** Имя пользователя базы данных */
define( 'DB_USER', 'wordpress_user' );

/** Пароль к базе данных */
define( 'DB_PASSWORD', 'admin' );

/** Имя сервера базы данных */
define( 'DB_HOST', 'localhost' );

/** Кодировка базы данных для создания таблиц. */
define( 'DB_CHARSET', 'utf8mb4' );

/** Схема сопоставления. Не меняйте, если не уверены. */
define( 'DB_COLLATE', '' );

/**#@+
 * Уникальные ключи и соли для аутентификации.
 *
 * Смените значение каждой константы на уникальную фразу. Можно сгенерировать их с помощью
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ сервиса ключей на WordPress.org}.
 *
 * Можно изменить их, чтобы сделать существующие файлы cookies недействительными.
 * Пользователям потребуется авторизоваться снова.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '>gs-lvMDX=Mk?DV~^l R6?H^|q}u)k(ogdm?x_4`S)*WaFwVGv(+:]1|*/}V;c,I' );
define( 'SECURE_AUTH_KEY',  'I&9s?X%ROb4HUK)?Nldw{TTiFjO_?X|D<CL:?H(.xl`4o_$%2dk$YU+SKIc]DpH7' );
define( 'LOGGED_IN_KEY',    'kkB Rf3+eQi@^IRU*)Q63POCYlr^`YB7Mo~tJ-(O1SY`-%7l4:BP8+~D={vAySZf' );
define( 'NONCE_KEY',        'G;Cdcw58,nt:GS`y0oMe*Pe|<5Sx,c$Wk.ErvPVn/JU=j.h.)u@>%+Zrs9&L~}<8' );
define( 'AUTH_SALT',        'njO8Y=E& k65^HLP15 raNKR:D@I#Vl~[1&.|C?Hg-U0)OiGk[@.=FA15.cq>[Y ' );
define( 'SECURE_AUTH_SALT', '{J!bCrB3T60W7tPe!C9h7:~93=?~hnNIVNjp]<OtSv_TyRZRJlrTP).Rm>l_E&E7' );
define( 'LOGGED_IN_SALT',   'Sn$nE~a;O(FNI[/9xN<u_u@J36+:)*ZZ;L;lf&=:0Bx7LifU}a|@BdBX1&1OCssU' );
define( 'NONCE_SALT',       '?+#gbDTk)g%cw&AIViblMGiD`Db2*PTUK;@A%#+2U]NKG;?mBOD.~Jk19?Q7[mG?' );

/**#@-*/

/**
 * Префикс таблиц в базе данных WordPress.
 *
 * Можно установить несколько сайтов в одну базу данных, если использовать
 * разные префиксы. Пожалуйста, указывайте только цифры, буквы и знак подчеркивания.
 *
 * В процессе установки указанный префикс добавляется к именам таблиц базы данных.
 * Если изменить это значение после установки WordPress, то сайт снова перейдёт
 * в режим установки.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * Для разработчиков: Режим отладки WordPress.
 *
 * Измените это значение на true, чтобы включить отображение уведомлений при разработке.
 * Разработчикам плагинов и тем настоятельно рекомендуется использовать WP_DEBUG
 * в своём рабочем окружении.
 *
 * Информацию о других отладочных константах можно найти в документации.
 *
 * @link https://ru.wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Произвольные значения добавляйте между этой строкой и надписью "дальше не редактируем". */



/* Это всё, дальше не редактируем. Успехов! */

/** Абсолютный путь к директории WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Инициализирует переменные WordPress и подключает файлы. */
require_once ABSPATH . 'wp-settings.php';
