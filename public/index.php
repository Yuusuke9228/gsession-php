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

// ワークフロー関連のルーティング
// ワークフロー管理画面
$router->get('/workflow', function () {
    $controller = new Controllers\WorkflowController();
    $controller->index();
}, true);

// テンプレート一覧
$router->get('/workflow/templates', function () {
    $controller = new Controllers\WorkflowController();
    $controller->templates();
}, true);

// テンプレート作成画面
$router->get('/workflow/create-template', function () {
    $controller = new Controllers\WorkflowController();
    $controller->createTemplate();
}, true);

// テンプレート編集画面
$router->get('/workflow/edit-template/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->editTemplate($params);
}, true);

// フォームデザイナー画面
$router->get('/workflow/design-form/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->designForm($params);
}, true);

// 承認経路設定画面
$router->get('/workflow/design-route/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->designRoute($params);
}, true);

// 申請一覧画面
$router->get('/workflow/requests', function () {
    $controller = new Controllers\WorkflowController();
    $controller->requests();
}, true);

// 承認待ち一覧画面
$router->get('/workflow/approvals', function () {
    $controller = new Controllers\WorkflowController();
    $controller->approvals();
}, true);

// 新規申請作成画面
$router->get('/workflow/create/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->create($params);
}, true);

// 申請編集画面
$router->get('/workflow/edit/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->edit($params);
}, true);

// 申請詳細画面
$router->get('/workflow/view/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->viewDetails($params);
}, true);

// 代理承認設定画面
$router->get('/workflow/delegates', function () {
    $controller = new Controllers\WorkflowController();
    $controller->delegates();
}, true);

// ワークフロー関連のルーティング
// ワークフロー一覧
$router->get('/workflow', function () {
    $controller = new Controllers\WorkflowController();
    $controller->index();
}, true);

// テンプレート一覧
$router->get('/workflow/templates', function () {
    $controller = new Controllers\WorkflowController();
    $controller->templates();
}, true);

// テンプレート作成
$router->get('/workflow/create-template', function () {
    $controller = new Controllers\WorkflowController();
    $controller->createTemplate();
}, true);

// テンプレート編集
$router->get('/workflow/edit-template/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->editTemplate($params);
}, true);

// フォームデザイナー
$router->get('/workflow/design-form/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->designForm($params);
}, true);

// 承認経路デザイナー
$router->get('/workflow/design-route/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->designRoute($params);
}, true);

// 申請一覧
$router->get('/workflow/requests', function () {
    $controller = new Controllers\WorkflowController();
    $controller->requests();
}, true);

// 承認待ち一覧
$router->get('/workflow/approvals', function () {
    $controller = new Controllers\WorkflowController();
    $controller->approvals();
}, true);

// 新規申請作成
$router->get('/workflow/create/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->create($params);
}, true);

// 申請編集
$router->get('/workflow/edit/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->edit($params);
}, true);

// 申請詳細
$router->get('/workflow/view/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    $controller->viewDetails($params);
}, true);

// 代理承認設定
$router->get('/workflow/delegates', function () {
    $controller = new Controllers\WorkflowController();
    $controller->delegates();
}, true);

// API ルート
// テンプレート関連API
$router->apiGet('/workflow/templates', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiGetAllTemplates($params);
}, true);

$router->apiGet('/workflow/templates/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiGetTemplate($params);
}, true);

$router->apiPost('/workflow/templates', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiCreateTemplate($params, $data);
}, true);

$router->apiPost('/workflow/templates/:id', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiUpdateTemplate($params, $data);
}, true);

$router->apiDelete('/workflow/templates/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiDeleteTemplate($params);
}, true);

// フォーム関連API
$router->apiGet('/workflow/templates/:id/form', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiGetTemplate($params);
}, true);

$router->apiPost('/workflow/templates/:id/form', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiSaveFormDefinitions($params, $data);
}, true);

$router->apiPost('/workflow/templates/:id/form-fields', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiAddFormField($params, $data);
}, true);

$router->apiPost('/workflow/templates/:id/form-fields/:field_id', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiUpdateFormField($params, $data);
}, true);

$router->apiDelete('/workflow/templates/:id/form-fields/:field_id', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiDeleteFormField($params);
}, true);

// 承認経路関連API
$router->apiGet('/workflow/templates/:id/route', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiGetTemplate($params);
}, true);

$router->apiPost('/workflow/templates/:id/route', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiSaveRouteDefinitions($params, $data);
}, true);

$router->apiPost('/workflow/templates/:id/route-steps', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiAddRouteStep($params, $data);
}, true);

$router->apiPost('/workflow/templates/:id/route-steps/:step_id', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiUpdateRouteStep($params, $data);
}, true);

$router->apiDelete('/workflow/templates/:id/route-steps/:step_id', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiDeleteRouteStep($params);
}, true);

// 申請関連API
$router->apiGet('/workflow/requests', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiGetRequests($params);
}, true);

$router->apiGet('/workflow/requests/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiGetRequest($params);
}, true);

$router->apiPost('/workflow/requests', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiCreateRequest($params, $data);
}, true);

$router->apiPost('/workflow/requests/:id', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiUpdateRequest($params, $data);
}, true);

$router->apiDelete('/workflow/requests/:id', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiCancelRequest($params);
}, true);

// 承認関連API
$router->apiPost('/workflow/requests/:id/approve', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiProcessApproval($params, $data);
}, true);

$router->apiPost('/workflow/requests/:id/comments', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiAddComment($params, $data);
}, true);

// 代理承認設定API
$router->apiPost('/workflow/delegates', function ($params, $data) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiAddDelegation($params, $data);
}, true);

// エクスポートAPI
$router->apiGet('/workflow/requests/:id/export/pdf', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiExportPdf($params);
}, true);

$router->apiGet('/workflow/requests/:id/export/csv', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiExportCsv($params);
}, true);

// 統計情報API
$router->apiGet('/workflow/stats', function ($params) {
    $controller = new Controllers\WorkflowController();
    return $controller->apiGetStats($params);
}, true);

// リクエストのディスパッチ（ルーティング処理の実行）
$router->dispatch();
