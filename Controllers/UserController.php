<?php
// controllers/UserController.php
namespace Controllers;

use Core\Auth;
use Models\User;
use Models\Organization;

class UserController {
    private $auth;
    private $model;
    
    public function __construct() {
        $this->auth = Auth::getInstance();
        $this->model = new User();
    }
    
    // ユーザー一覧ページを表示
    public function index() {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            echo '認証されていません。 BASE_PATH: ' . BASE_PATH;
            exit;
        }
        
        // ページネーション
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);
        $limit = 20;
        
        // 検索条件
        $search = $_GET['search'] ?? null;
        
        // ユーザーリストを取得
        $users = $this->model->getAll($page, $limit, $search);
        $totalUsers = $this->model->getCount($search);
        $totalPages = ceil($totalUsers / $limit);
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/user/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // ユーザー作成ページを表示
    public function create() {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        // 権限チェック
        if (!$this->auth->isAdmin()) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // 組織リストを取得
        $orgModel = new Organization();
        $organizations = $orgModel->getAll();
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/user/create.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // ユーザー編集ページを表示
    public function edit($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // 権限チェック（管理者または自分自身の編集のみ許可）
        if (!$this->auth->isAdmin() && $this->auth->id() != $id) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // ユーザー情報を取得
        $user = $this->model->getById($id);
        if (!$user) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // ユーザーの所属組織を取得
        $userOrganizations = $this->model->getUserOrganizations($id);
        $userOrgIds = array_column($userOrganizations, 'id');
        
        // 主組織のID
        $primaryOrgId = null;
        foreach ($userOrganizations as $org) {
            if ($org['is_primary']) {
                $primaryOrgId = $org['id'];
                break;
            }
        }
        
        // 組織リストを取得
        $orgModel = new Organization();
        $organizations = $orgModel->getAll();
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/user/edit.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // ユーザーの詳細ページを表示
    public function view($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // ユーザー情報を取得
        $user = $this->model->getById($id);
        if (!$user) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // ユーザーの所属組織を取得
        $userOrganizations = $this->model->getUserOrganizations($id);
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/user/view.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // パスワード変更ページを表示
    public function changePassword($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // 権限チェック（管理者または自分自身のパスワード変更のみ許可）
        if (!$this->auth->isAdmin() && $this->auth->id() != $id) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // ユーザー情報を取得
        $user = $this->model->getById($id);
        if (!$user) {
            header('Location:' . BASE_PATH . '/users');
            exit;
        }
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/user/change_password.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // API: 全ユーザーを取得
    public function apiGetAll($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // ページネーション
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;
        
        // 検索条件
        $search = $params['search'] ?? null;
        
        $users = $this->model->getAll($page, $limit, $search);
        $totalUsers = $this->model->getCount($search);
        $totalPages = ceil($totalUsers / $limit);
        
        return [
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'total' => $totalUsers,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ]
        ];
    }
    
    // API: 特定のユーザーを取得
    public function apiGetOne($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        $user = $this->model->getById($id);
        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        
        // パスワードハッシュは返さない
        unset($user['password']);
        
        // ユーザーの所属組織を取得
        $userOrganizations = $this->model->getUserOrganizations($id);
        
        return [
            'success' => true,
            'data' => [
                'user' => $user,
                'organizations' => $userOrganizations
            ]
        ];
    }
    
    // API: ユーザーを作成
    public function apiCreate($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // 権限チェック
        if (!$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        // バリデーション
        if (empty($data['username']) || empty($data['password']) || 
            empty($data['email']) || empty($data['first_name']) || 
            empty($data['last_name'])) {
            return ['error' => 'All required fields must be provided', 'code' => 400];
        }
        
        // ユーザー名とメールアドレスの重複チェック
        if ($this->model->getByUsername($data['username'])) {
            return ['error' => 'Username already exists', 'code' => 400];
        }
        
        if ($this->model->getByEmail($data['email'])) {
            return ['error' => 'Email already exists', 'code' => 400];
        }
        
        $id = $this->model->create($data);
        if (!$id) {
            return ['error' => 'Failed to create user', 'code' => 500];
        }
        
        $user = $this->model->getById($id);
        // パスワードハッシュは返さない
        unset($user['password']);
        
        return ['success' => true, 'data' => $user, 'message' => 'ユーザーを作成しました'];
    }
    
    // API: ユーザーを更新
    public function apiUpdate($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        // 権限チェック（管理者または自分自身の編集のみ許可）
        if (!$this->auth->isAdmin() && $this->auth->id() != $id) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        // ユーザーの存在チェック
        $user = $this->model->getById($id);
        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        
        // 一般ユーザーが編集できるフィールドを制限
        if (!$this->auth->isAdmin() && $this->auth->id() == $id) {
            // パスワード変更は専用APIで行う
            unset($data['password']);
            
            // 役割や状態は変更不可
            unset($data['role']);
            unset($data['status']);
            
            // ユーザー名も変更不可
            unset($data['username']);
        }
        
        // ユーザー名とメールアドレスの重複チェック
        if (isset($data['username']) && $data['username'] !== $user['username'] && 
            $this->model->getByUsername($data['username'])) {
            return ['error' => 'Username already exists', 'code' => 400];
        }
        
        if (isset($data['email']) && $data['email'] !== $user['email'] && 
            $this->model->getByEmail($data['email'])) {
            return ['error' => 'Email already exists', 'code' => 400];
        }
        
        $success = $this->model->update($id, $data);
        if (!$success) {
            return ['error' => 'Failed to update user', 'code' => 500];
        }
        
        $user = $this->model->getById($id);
        // パスワードハッシュは返さない
        unset($user['password']);
        
        return ['success' => true, 'data' => $user, 'message' => 'ユーザー情報を更新しました'];
    }
    
    // API: ユーザーを削除
    public function apiDelete($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // 権限チェック
        if (!$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        // 自分自身は削除不可
        if ($this->auth->id() == $id) {
            return ['error' => 'Cannot delete yourself', 'code' => 400];
        }
        
        // ユーザーの存在チェック
        $user = $this->model->getById($id);
        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        
        $success = $this->model->delete($id);
        if (!$success) {
            return ['error' => 'Failed to delete user', 'code' => 500];
        }
        
        return ['success' => true, 'message' => 'ユーザーを削除しました'];
    }
    
    // API: パスワードを変更
    public function apiChangePassword($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        // 権限チェック（管理者または自分自身のパスワード変更のみ許可）
        if (!$this->auth->isAdmin() && $this->auth->id() != $id) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        // ユーザーの存在チェック
        $user = $this->model->getById($id);
        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        
        // バリデーション
        if (empty($data['new_password'])) {
            return ['error' => 'New password is required', 'code' => 400];
        }
        
        // 自分自身のパスワード変更時は現在のパスワードが必要
        if ($this->auth->id() == $id && !$this->auth->isAdmin()) {
            if (empty($data['current_password'])) {
                return ['error' => 'Current password is required', 'code' => 400];
            }
            
            if (!password_verify($data['current_password'], $user['password'])) {
                return ['error' => 'Current password is incorrect', 'code' => 400];
            }
        }
        
        $success = $this->model->changePassword($id, $data['new_password']);
        if (!$success) {
            return ['error' => 'Failed to change password', 'code' => 500];
        }
        
        return ['success' => true, 'message' => 'パスワードを変更しました'];
    }
    
    // API: ユーザーの組織を取得
    public function apiGetUserOrganizations($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        // ユーザーの存在チェック
        $user = $this->model->getById($id);
        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        
        $organizations = $this->model->getUserOrganizations($id);
        
        return ['success' => true, 'data' => $organizations];
    }
    
    // API: 主組織を変更
    public function apiChangePrimaryOrganization($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id || empty($data['organization_id'])) {
            return ['error' => 'Invalid parameters', 'code' => 400];
        }
        
        // 権限チェック（管理者または自分自身の主組織変更のみ許可）
        if (!$this->auth->isAdmin() && $this->auth->id() != $id) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        // ユーザーの存在チェック
        $user = $this->model->getById($id);
        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        
        $success = $this->model->changePrimaryOrganization($id, $data['organization_id']);
        if (!$success) {
            return ['error' => 'Failed to change primary organization', 'code' => 500];
        }
        
        return ['success' => true, 'message' => '主組織を変更しました'];
    }
}   