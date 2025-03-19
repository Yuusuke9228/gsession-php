<?php
// public/index.php - アプリケーションのエントリーポイント

// 基本設定
session_start();
date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', true);
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

// アプリケーションのベースパスを設定
$basePath = dirname($_SERVER["SCRIPT_NAME"]);
if ($basePath == "/") $basePath = "";
define("BASE_PATH", $basePath);

// オートローダー設定
spl_autoload_register(function ($class) {
    // 名前空間を考慮してファイルパスに変換
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . '/../' . $path . '.php';

    // 標準パスでファイルを探す
    if (file_exists($file)) {
        require_once $file;
        return true;
    }

    // 小文字のパスで試す
    $lowercasePath = strtolower($path);
    $file = __DIR__ . '/../' . $lowercasePath . '.php';

    if (file_exists($file)) {
        require_once $file;
        return true;
    }

    // 大文字と小文字を入れ替えたパスで試す (Core -> core)
    $altPath = str_ireplace('Core', 'core', $path);
    $file = __DIR__ . '/../' . $altPath . '.php';

    if (file_exists($file)) {
        require_once $file;
        return true;
    }

    return false;
});

// コアクラスのインスタンス取得
$router = Core\Router::getInstance();
$auth = Core\Auth::getInstance();
$config = require_once __DIR__ . '/../config/config.php';

// Remember Me トークンからの認証
if (!$auth->check()) {
    $auth->authenticateFromRememberToken();
}

// ルーティングの設定

// ホームページ
$router->get('/', function () use ($auth) {
    if ($auth->check()) {
        header('Location: ' . BASE_PATH . '/schedule');
    } else {
        header('Location: ' . BASE_PATH . '/login');
    }
});

// 認証関連
$router->get('/login', function () use ($auth) {
    if ($auth->check()) {
        header('Location: ' . BASE_PATH . '/schedule');
        exit;
    }

    require_once __DIR__ . '/../views/auth/login.php';
});

$router->post('/login', function () use ($auth) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;

    if ($auth->login($username, $password, $remember)) {
        $redirect = $_GET['redirect'] ?? '/schedule';
        header('Location: ' . BASE_PATH . $redirect);
    } else {
        $_SESSION['login_error'] = 'ユーザー名またはパスワードが正しくありません';
        header('Location: ' . BASE_PATH . '/login');
    }
});

$router->get('/logout', function () use ($auth) {
    $auth->logout();
    header('Location: ' . BASE_PATH . '/login');
});

// 組織管理
$router->get('/organizations', function () {
    $controller = new Controllers\OrganizationController();
    $controller->index();
}, true);

$router->get('/organizations/create', function () {
    $controller = new Controllers\OrganizationController();
    $controller->create();
}, true);

$router->get('/organizations/edit/:id', function ($params) {
    $controller = new Controllers\OrganizationController();
    $controller->edit($params);
}, true);

$router->get('/organizations/view/:id', function ($params) {
    $controller = new Controllers\OrganizationController();
    $controller->viewDetails($params);
}, true);

// ユーザー管理
$router->get('/users', function () {
    $controller = new Controllers\UserController();
    $controller->index();
}, true);

$router->get('/users/create', function () {
    $controller = new Controllers\UserController();
    $controller->create();
}, true);

$router->get('/users/edit/:id', function ($params) {
    $controller = new Controllers\UserController();
    $controller->edit($params);
}, true);

$router->get('/users/view/:id', function ($params) {
    $controller = new Controllers\UserController();
    $controller->viewDetails($params);
}, true);

$router->get('/users/change-password/:id', function ($params) {
    $controller = new Controllers\UserController();
    $controller->changePassword($params);
}, true);

// スケジュール管理
$router->get('/schedule', function () {
    header('Location:' . BASE_PATH . '/schedule/month');
    exit;
}, true);

$router->get('/schedule/day', function () {
    $controller = new Controllers\ScheduleController();
    $controller->day();
}, true);

$router->get('/schedule/week', function () {
    $controller = new Controllers\ScheduleController();
    $controller->week();
}, true);

$router->get('/schedule/month', function () {
    $controller = new Controllers\ScheduleController();
    $controller->month();
}, true);

$router->get('/schedule/create', function () {
    $controller = new Controllers\ScheduleController();
    $controller->create();
}, true);

$router->get('/schedule/edit/:id', function ($params) {
    $controller = new Controllers\ScheduleController();
    $controller->edit($params);
}, true);

$router->get('/schedule/view/:id', function ($params) {
    $controller = new Controllers\ScheduleController();
    $controller->viewDetails($params);
}, true);

// API ルート

// 組織管理API
$router->apiGet('/organizations', function () {
    $controller = new Controllers\OrganizationController();
    return $controller->apiGetAll();
}, true);

$router->apiGet('/organizations/tree', function () {
    $controller = new Controllers\OrganizationController();
    return $controller->apiGetTree();
}, true);

$router->apiGet('/organizations/:id', function ($params) {
    $controller = new Controllers\OrganizationController();
    return $controller->apiGetOne($params);
}, true);

$router->apiPost('/organizations', function ($params, $data) {
    $controller = new Controllers\OrganizationController();
    return $controller->apiCreate($params, $data);
}, true);

$router->apiPost('/organizations/:id', function ($params, $data) {
    $controller = new Controllers\OrganizationController();
    return $controller->apiUpdate($params, $data);
}, true);

$router->apiDelete('/organizations/:id', function ($params) {
    $controller = new Controllers\OrganizationController();
    return $controller->apiDelete($params);
}, true);

$router->apiPost('/organizations/:id/move', function ($params, $data) {
    $controller = new Controllers\OrganizationController();
    return $controller->apiUpdateOrder($params, $data);
}, true);

// 組織コード重複チェックAPI
$router->apiGet('/organizations/check-code', function ($params) {
    $controller = new Controllers\OrganizationController();
    return $controller->apiCheckCodeUnique($params);
}, true);

// 組織のユーザー一覧を取得
$router->apiGet('/organizations/:id/users', function ($params) {
    $controller = new Controllers\OrganizationController();
    return $controller->apiGetUsers($params);
}, true);

// ユーザー管理API
$router->apiGet('/users', function ($params) {
    $controller = new Controllers\UserController();
    return $controller->apiGetAll($params);
}, true);

$router->apiGet('/users/:id', function ($params) {
    $controller = new Controllers\UserController();
    return $controller->apiGetOne($params);
}, true);

$router->apiGet('/users/:id/organizations', function ($params) {
    $controller = new Controllers\UserController();
    return $controller->apiGetUserOrganizations($params);
}, true);

$router->apiPost('/users', function ($params, $data) {
    $controller = new Controllers\UserController();
    return $controller->apiCreate($params, $data);
}, true);

$router->apiPost('/users/:id', function ($params, $data) {
    $controller = new Controllers\UserController();
    return $controller->apiUpdate($params, $data);
}, true);

$router->apiDelete('/users/:id', function ($params) {
    $controller = new Controllers\UserController();
    return $controller->apiDelete($params);
}, true);

$router->apiPost('/users/:id/change-password', function ($params, $data) {
    $controller = new Controllers\UserController();
    return $controller->apiChangePassword($params, $data);
}, true);

$router->apiPost('/users/:id/primary-organization', function ($params, $data) {
    $controller = new Controllers\UserController();
    return $controller->apiChangePrimaryOrganization($params, $data);
}, true);

// スケジュール管理API
$router->apiGet('/schedule/day', function ($params) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiGetDay($params);
}, true);

$router->apiGet('/schedule/week', function ($params) {
    $controller = new Controllers\ScheduleController();
    error_log(json_encode(['debug:apigetweek:' => $params]));
    return $controller->apiGetWeek($params);
}, true);

$router->apiGet('/schedule/month', function ($params) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiGetMonth($params);
}, true);

$router->apiGet('/schedule/range', function ($params) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiGetByDateRange($params);
}, true);

$router->apiGet('/schedule/:id', function ($params) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiGetOne($params);
}, true);

$router->apiPost('/schedule', function ($params, $data) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiCreate($params, $data);
}, true);

$router->apiPost('/schedule/:id', function ($params, $data) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiUpdate($params, $data);
}, true);

$router->apiDelete('/schedule/:id', function ($params) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiDelete($params);
}, true);

$router->apiPost('/schedule/:id/participation-status', function ($params, $data) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiUpdateParticipantStatus($params, $data);
}, true);

$router->apiPost('/schedule/:id/participants', function ($params, $data) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiAddParticipant($params, $data);
}, true);

$router->apiDelete('/schedule/:id/participants', function ($params, $data) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiRemoveParticipant($params, $data);
}, true);

$router->apiPost('/schedule/:id/organizations', function ($params, $data) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiAddOrganization($params, $data);
}, true);

$router->apiDelete('/schedule/:id/organizations', function ($params, $data) {
    $controller = new Controllers\ScheduleController();
    return $controller->apiRemoveOrganization($params, $data);
}, true);

// アクティブユーザー取得API
$router->apiGet('/active-users', function () {
    $controller = new Controllers\UserController();
    return $controller->apiGetActiveUsers();
}, true);

// リクエストのディスパッチ（ルーティング処理の実行）
$router->dispatch();
