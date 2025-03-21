<?php
// controllers/WorkflowController.php
namespace Controllers;

use Core\Controller;
use Core\Database;
use Core\Auth;
use Models\Workflow;
use Models\User;
use Models\Organization;

class WorkflowController extends Controller
{
    private $db;
    private $model;
    private $userModel;
    private $organizationModel;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->model = new Workflow();
        $this->userModel = new User();
        $this->organizationModel = new Organization();

        // 認証チェック
        if (!$this->auth->check()) {
            $this->redirect(BASE_PATH . '/login');
        }
    }

    /**
     * ワークフロー一覧ページを表示
     */
    public function index()
    {
        $viewData = [
            'title' => 'ワークフロー管理',
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/index', $viewData);
    }

    /**
     * ワークフローテンプレート一覧ページを表示
     */
    public function templates()
    {
        // ページネーション
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);
        $limit = 20;

        // 検索条件
        $search = $_GET['search'] ?? null;

        // テンプレートリストを取得
        $templates = $this->model->getAllTemplates($page, $limit, $search);
        $totalTemplates = $this->model->getTemplateCount($search);
        $totalPages = ceil($totalTemplates / $limit);

        $viewData = [
            'title' => 'ワークフローテンプレート',
            'templates' => $templates,
            'totalTemplates' => $totalTemplates,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/templates', $viewData);
    }

    /**
     * テンプレート作成ページを表示
     */
    public function createTemplate()
    {
        // 権限チェック
        if (!$this->auth->isAdmin()) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        $viewData = [
            'title' => '新規テンプレート作成',
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/template_form', $viewData);
    }

    /**
     * テンプレート編集ページを表示
     */
    public function editTemplate($params)
    {
        // 権限チェック
        if (!$this->auth->isAdmin()) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // テンプレート情報を取得
        $template = $this->model->getTemplateById($id);
        if (!$template) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // フォーム定義を取得
        $formDefinitions = $this->model->getFormDefinitions($id);

        // 承認経路を取得
        $routeDefinitions = $this->model->getRouteDefinitions($id);

        $viewData = [
            'title' => 'テンプレート編集',
            'template' => $template,
            'formDefinitions' => $formDefinitions,
            'routeDefinitions' => $routeDefinitions,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/template_form', $viewData);
    }

    /**
     * テンプレートのフォームデザイナーページを表示
     */
    public function designForm($params)
    {
        // 権限チェック
        if (!$this->auth->isAdmin()) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // テンプレート情報を取得
        $template = $this->model->getTemplateById($id);
        if (!$template) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // フォーム定義を取得
        $formDefinitions = $this->model->getFormDefinitions($id);

        $viewData = [
            'title' => 'フォームデザイン',
            'template' => $template,
            'formDefinitions' => $formDefinitions,
            'jsFiles' => ['workflow.js', 'workflow-form-designer.js']
        ];

        $this->view('workflow/form_designer', $viewData);
    }

    /**
     * テンプレートの承認経路設定ページを表示
     */
    public function designRoute($params)
    {
        // 権限チェック
        if (!$this->auth->isAdmin()) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // テンプレート情報を取得
        $template = $this->model->getTemplateById($id);
        if (!$template) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // 承認経路を取得
        $routeDefinitions = $this->model->getRouteDefinitions($id);

        // ユーザー一覧を取得
        $users = $this->userModel->getActiveUsers();

        // 組織一覧を取得
        $organizations = $this->organizationModel->getAll();

        $viewData = [
            'title' => '承認経路設定',
            'template' => $template,
            'routeDefinitions' => $routeDefinitions,
            'users' => $users,
            'organizations' => $organizations,
            'jsFiles' => ['workflow.js', 'workflow-route-designer.js']
        ];

        $this->view('workflow/route_designer', $viewData);
    }

    /**
     * 申請一覧ページを表示
     */ public function requests()
    {
        // フィルタリング条件
        $filters = [
            'status' => $_GET['status'] ?? null,
            'template_id' => $_GET['template_id'] ?? null,
            'search' => $_GET['search'] ?? null,
        ];

        // 権限に基づいてフィルタリング
        $userId = $this->auth->id();
        $isAdmin = $this->auth->isAdmin();

        // 管理者以外は自分の申請のみ表示
        if (!$isAdmin) {
            $filters['requester_id'] = $userId;
        }

        // ページネーション
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);
        $limit = 20;

        // 申請リストを取得
        $requests = $this->model->getRequests($filters, $page, $limit);
        $totalRequests = $this->model->getRequestCount($filters);
        $totalPages = ceil($totalRequests / $limit);

        // テンプレート一覧を取得（フィルター用）
        $templates = $this->model->getAllTemplates(1, 100);

        $viewData = [
            'title' => '申請一覧',
            'requests' => $requests,
            'totalRequests' => $totalRequests,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'templates' => $templates,
            'isAdmin' => $isAdmin,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/requests', $viewData);
    }

    /**
     * 承認待ち一覧ページを表示
     */
    public function approvals()
    {
        $userId = $this->auth->id();

        // フィルタリング条件
        $filters = [
            'pending_approval' => true,
            'user_id' => $userId,
            'template_id' => $_GET['template_id'] ?? null,
            'search' => $_GET['search'] ?? null,
        ];

        // ページネーション
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page);
        $limit = 20;

        // 承認待ち申請リストを取得
        $requests = $this->model->getRequests($filters, $page, $limit);
        $totalRequests = $this->model->getRequestCount($filters);
        $totalPages = ceil($totalRequests / $limit);

        // テンプレート一覧を取得（フィルター用）
        $templates = $this->model->getAllTemplates(1, 100);

        $viewData = [
            'title' => '承認待ち一覧',
            'requests' => $requests,
            'totalRequests' => $totalRequests,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'templates' => $templates,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/approvals', $viewData);
    }

    /**
     * 新規申請ページを表示
     */
    public function create($params)
    {
        $templateId = $params['id'] ?? null;
        if (!$templateId) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // テンプレート情報を取得
        $template = $this->model->getTemplateById($templateId);
        if (!$template) {
            $this->redirect(BASE_PATH . '/workflow/templates');
        }

        // フォーム定義を取得
        $formDefinitions = $this->model->getFormDefinitions($templateId);

        $viewData = [
            'title' => '新規申請作成',
            'template' => $template,
            'formDefinitions' => $formDefinitions,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/request_form', $viewData);
    }

    /**
     * 申請編集ページを表示
     */
    public function edit($params)
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->redirect(BASE_PATH . '/workflow/requests');
        }

        // 申請情報を取得
        $request = $this->model->getRequestById($id);
        if (!$request) {
            $this->redirect(BASE_PATH . '/workflow/requests');
        }

        // 権限チェック（申請者のみ編集可能、ただしドラフト状態の場合のみ）
        if ($request['requester_id'] != $this->auth->id() || $request['status'] !== 'draft') {
            $this->redirect(BASE_PATH . '/workflow/requests');
        }

        // テンプレート情報を取得
        $template = $this->model->getTemplateById($request['template_id']);

        // フォーム定義を取得
        $formDefinitions = $this->model->getFormDefinitions($request['template_id']);

        // フォームデータを取得
        $formData = $this->model->getRequestData($id);

        $viewData = [
            'title' => '申請編集',
            'request' => $request,
            'template' => $template,
            'formDefinitions' => $formDefinitions,
            'formData' => $formData,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/request_form', $viewData);
    }

    /**
     * 申請詳細ページを表示
     */
    public function viewDetails($params)
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->redirect(BASE_PATH . '/workflow/requests');
        }

        // 申請情報を取得
        $request = $this->model->getRequestById($id);
        if (!$request) {
            $this->redirect(BASE_PATH . '/workflow/requests');
        }

        // 権限チェック（管理者、申請者、承認者のみ閲覧可能）
        $userId = $this->auth->id();
        $isAdmin = $this->auth->isAdmin();
        $isRequester = ($request['requester_id'] == $userId);
        
        // 承認者かどうかチェック
        $sql = "SELECT COUNT(*) as count FROM workflow_approvals 
                WHERE request_id = ? AND approver_id = ?";
        $isApprover = $this->db->fetch($sql, [$id, $userId])['count'] > 0;
        
        if (!$isAdmin && !$isRequester && !$isApprover) {
            $this->redirect(BASE_PATH . '/workflow/requests');
        }

        // テンプレート情報を取得
        $template = $this->model->getTemplateById($request['template_id']);

        // フォーム定義を取得
        $formDefinitions = $this->model->getFormDefinitions($request['template_id']);

        // フォームデータを取得
        $formData = $this->model->getRequestData($id);

        // 添付ファイルを取得
        $attachments = $this->model->getRequestAttachments($id);

        // 承認履歴を取得
        $approvals = $this->model->getRequestApprovals($id);

        // 現在のユーザーの承認タスクを取得
        $currentApproval = null;
        if (!$isAdmin && !$isRequester) {
            $sql = "SELECT * FROM workflow_approvals 
                    WHERE request_id = ? AND approver_id = ? AND status = 'pending' 
                    AND step_number = ? LIMIT 1";
            $currentApproval = $this->db->fetch($sql, [
                $id, 
                $userId, 
                $request['current_step']
            ]);
        }

        // コメントを取得
        $comments = $this->model->getComments($id);

        $viewData = [
            'title' => '申請詳細：' . $request['title'],
            'request' => $request,
            'template' => $template,
            'formDefinitions' => $formDefinitions,
            'formData' => $formData,
            'attachments' => $attachments,
            'approvals' => $approvals,
            'currentApproval' => $currentApproval,
            'comments' => $comments,
            'isAdmin' => $isAdmin,
            'isRequester' => $isRequester,
            'isApprover' => $isApprover,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/request_view', $viewData);
    }

    /**
     * 代理承認設定ページを表示
     */
    public function delegates()
    {
        // ユーザーIDを取得
        $userId = $this->auth->id();

        // 代理承認設定を取得
        $delegations = $this->model->getUserDelegations($userId);

        // ユーザー一覧を取得
        $users = $this->userModel->getActiveUsers();

        // テンプレート一覧を取得
        $templates = $this->model->getAllTemplates(1, 100);

        $viewData = [
            'title' => '代理承認設定',
            'delegations' => $delegations,
            'users' => $users,
            'templates' => $templates,
            'jsFiles' => ['workflow.js']
        ];

        $this->view('workflow/delegates', $viewData);
    }

    /* API メソッド */

    /**
     * API: 全テンプレートを取得
     */
    public function apiGetAllTemplates($params)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // ページネーション
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        // 検索条件
        $search = $params['search'] ?? null;

        $templates = $this->model->getAllTemplates($page, $limit, $search);
        $totalTemplates = $this->model->getTemplateCount($search);
        $totalPages = ceil($totalTemplates / $limit);

        return [
            'success' => true,
            'data' => [
                'templates' => $templates,
                'pagination' => [
                    'total' => $totalTemplates,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ]
        ];
    }

    /**
     * API: 特定のテンプレートを取得
     */
    public function apiGetTemplate($params)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }

        $template = $this->model->getTemplateById($id);
        if (!$template) {
            return ['error' => 'Template not found', 'code' => 404];
        }

        // フォーム定義を取得
        $formDefinitions = $this->model->getFormDefinitions($id);

        // 承認経路を取得
        $routeDefinitions = $this->model->getRouteDefinitions($id);

        return [
            'success' => true,
            'data' => [
                'template' => $template,
                'form_definitions' => $formDefinitions,
                'route_definitions' => $routeDefinitions
            ]
        ];
    }

    /**
     * API: テンプレートを作成
     */
    public function apiCreateTemplate($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // 権限チェック
        if (!$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        // バリデーション
        if (empty($data['name'])) {
            return ['error' => 'Template name is required', 'code' => 400];
        }

        // 作成者IDを追加
        $data['creator_id'] = $this->auth->id();

        $id = $this->model->createTemplate($data);
        if (!$id) {
            return ['error' => 'Failed to create template', 'code' => 500];
        }

        $template = $this->model->getTemplateById($id);

        return [
            'success' => true,
            'data' => $template,
            'message' => 'テンプレートを作成しました',
            'redirect' => BASE_PATH . '/workflow/templates'
        ];
    }

    /**
     * API: テンプレートを更新
     */
    public function apiUpdateTemplate($params, $data)
    {
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

        // テンプレートの存在チェック
        $template = $this->model->getTemplateById($id);
        if (!$template) {
            return ['error' => 'Template not found', 'code' => 404];
        }

        // バリデーション
        if (empty($data['name'])) {
            return ['error' => 'Template name is required', 'code' => 400];
        }

        $success = $this->model->updateTemplate($id, $data);
        if (!$success) {
            return ['error' => 'Failed to update template', 'code' => 500];
        }

        $template = $this->model->getTemplateById($id);

        return [
            'success' => true,
            'data' => $template,
            'message' => 'テンプレートを更新しました',
            'redirect' => BASE_PATH . '/workflow/templates'
        ];
    }

    /**
     * API: テンプレートを削除
     */
    public function apiDeleteTemplate($params)
    {
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

        // テンプレートの存在チェック
        $template = $this->model->getTemplateById($id);
        if (!$template) {
            return ['error' => 'Template not found', 'code' => 404];
        }

        $success = $this->model->deleteTemplate($id);
        if (!$success) {
            return ['error' => 'Cannot delete template with existing requests', 'code' => 400];
        }

        return [
            'success' => true,
            'message' => 'テンプレートを削除しました',
            'redirect' => BASE_PATH . '/workflow/templates'
        ];
    }

    /**
     * API: フォームフィールドを追加
     */
    public function apiAddFormField($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // 権限チェック
        if (!$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        $templateId = $params['id'] ?? null;
        if (!$templateId) {
            return ['error' => 'Invalid template ID', 'code' => 400];
        }

        // テンプレートの存在チェック
        $template = $this->model->getTemplateById($templateId);
        if (!$template) {
            return ['error' => 'Template not found', 'code' => 404];
        }

        // データに必須テンプレートIDを追加
        $data['template_id'] = $templateId;

        // バリデーション
        if (empty($data['field_id']) || empty($data['field_type']) || empty($data['label'])) {
            return ['error' => 'Field ID, type and label are required', 'code' => 400];
        }

        $id = $this->model->addFormField($data);
        if (!$id) {
            return ['error' => 'Failed to add form field', 'code' => 500];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'field_id' => $data['field_id'],
                'field_type' => $data['field_type'],
                'label' => $data['label']
            ],
            'message' => 'フォームフィールドを追加しました'
        ];
    }

    /**
     * API: フォームフィールドを更新
     */
    public function apiUpdateFormField($params, $data)
    {
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
            return ['error' => 'Invalid field ID', 'code' => 400];
        }

        // バリデーション
        if (empty($data['field_id']) || empty($data['field_type']) || empty($data['label'])) {
            return ['error' => 'Field ID, type and label are required', 'code' => 400];
        }

        $success = $this->model->updateFormField($id, $data);
        if (!$success) {
            return ['error' => 'Failed to update form field', 'code' => 500];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'field_id' => $data['field_id'],
                'field_type' => $data['field_type'],
                'label' => $data['label']
            ],
            'message' => 'フォームフィールドを更新しました'
        ];
    }

    /**
     * API: フォームフィールドを削除
     */
    public function apiDeleteFormField($params)
    {
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
            return ['error' => 'Invalid field ID', 'code' => 400];
        }

        $success = $this->model->deleteFormField($id);
        if (!$success) {
            return ['error' => 'Failed to delete form field', 'code' => 500];
        }

        return [
            'success' => true,
            'message' => 'フォームフィールドを削除しました'
        ];
    }

    /**
     * API: 承認ステップを追加
     */
    public function apiAddRouteStep($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // 権限チェック
        if (!$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        $templateId = $params['id'] ?? null;
        if (!$templateId) {
            return ['error' => 'Invalid template ID', 'code' => 400];
        }

        // テンプレートの存在チェック
        $template = $this->model->getTemplateById($templateId);
        if (!$template) {
            return ['error' => 'Template not found', 'code' => 404];
        }

        // データに必須テンプレートIDを追加
        $data['template_id'] = $templateId;

        // バリデーション
        if (empty($data['step_number']) || empty($data['step_name']) || empty($data['approver_type'])) {
            return ['error' => 'Step number, name and approver type are required', 'code' => 400];
        }

        $id = $this->model->addRouteStep($data);
        if (!$id) {
            return ['error' => 'Failed to add route step', 'code' => 500];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'step_number' => $data['step_number'],
                'step_name' => $data['step_name'],
                'approver_type' => $data['approver_type']
            ],
            'message' => '承認ステップを追加しました'
        ];
    }

    /**
     * API: 承認ステップを更新
     */
    public function apiUpdateRouteStep($params, $data)
    {
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
            return ['error' => 'Invalid step ID', 'code' => 400];
        }

        // バリデーション
        if (empty($data['step_number']) || empty($data['step_name']) || empty($data['approver_type'])) {
            return ['error' => 'Step number, name and approver type are required', 'code' => 400];
        }

        $success = $this->model->updateRouteStep($id, $data);
        if (!$success) {
            return ['error' => 'Failed to update route step', 'code' => 500];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $id,
                'step_number' => $data['step_number'],
                'step_name' => $data['step_name'],
                'approver_type' => $data['approver_type']
            ],
            'message' => '承認ステップを更新しました'
        ];
    }

    /**
     * API: 承認ステップを削除
     */
    public function apiDeleteRouteStep($params)
    {
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
            return ['error' => 'Invalid step ID', 'code' => 400];
        }

        $success = $this->model->deleteRouteStep($id);
        if (!$success) {
            return ['error' => 'Failed to delete route step', 'code' => 500];
        }

        return [
            'success' => true,
            'message' => '承認ステップを削除しました'
        ];
    }

    /**
     * API: 申請を作成
     */
    public function apiCreateRequest($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // バリデーション
        if (empty($data['template_id']) || empty($data['title'])) {
            return ['error' => 'Template ID and title are required', 'code' => 400];
        }

        // 申請者IDを追加
        $data['requester_id'] = $this->auth->id();

        // ファイル処理
        if (isset($_FILES) && !empty($_FILES)) {
            $data['files'] = $this->processRequestFiles($_FILES);
        }

        try {
            $id = $this->model->createRequest($data);
            
            if (!$id) {
                return ['error' => 'Failed to create request', 'code' => 500];
            }

            $request = $this->model->getRequestById($id);

            return [
                'success' => true,
                'data' => $request,
                'message' => '申請を作成しました',
                'redirect' => BASE_PATH . '/workflow/requests'
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'code' => 500];
        }
    }

    /**
     * API: 申請を更新
     */
    public function apiUpdateRequest($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }

        // 申請の存在チェック
        $request = $this->model->getRequestById($id);
        if (!$request) {
            return ['error' => 'Request not found', 'code' => 404];
        }

        // 権限チェック（申請者のみ編集可能、ただしドラフト状態の場合のみ）
        if ($request['requester_id'] != $this->auth->id() || $request['status'] !== 'draft') {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        // バリデーション
        if (empty($data['title'])) {
            return ['error' => 'Title is required', 'code' => 400];
        }

        // ファイル処理
        if (isset($_FILES) && !empty($_FILES)) {
            $data['files'] = $this->processRequestFiles($_FILES);
        }

        try {
            $success = $this->model->updateRequest($id, $data);
            
            if (!$success) {
                return ['error' => 'Failed to update request', 'code' => 500];
            }

            $request = $this->model->getRequestById($id);

            return [
                'success' => true,
                'data' => $request,
                'message' => '申請を更新しました',
                'redirect' => BASE_PATH . '/workflow/requests'
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'code' => 500];
        }
    }

    /**
     * API: 申請を承認/却下
     */
    public function apiProcessApproval($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $requestId = $params['id'] ?? null;
        if (!$requestId) {
            return ['error' => 'Invalid request ID', 'code' => 400];
        }

        // 申請の存在チェック
        $request = $this->model->getRequestById($requestId);
        if (!$request || $request['status'] !== 'pending') {
            return ['error' => 'Valid pending request not found', 'code' => 404];
        }

        // アクションチェック
        $action = $data['action'] ?? '';
        if ($action !== 'approved' && $action !== 'rejected') {
            return ['error' => 'Invalid action', 'code' => 400];
        }

        $userId = $this->auth->id();

        try {
            $success = $this->model->processApproval($requestId, $userId, $data);
            
            if (!$success) {
                return ['error' => 'Failed to process approval', 'code' => 500];
            }

            $actionText = $action === 'approved' ? '承認' : '却下';

            return [
                'success' => true,
                'message' => '申請を' . $actionText . 'しました',
                'redirect' => BASE_PATH . '/workflow/approvals'
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'code' => 500];
        }
    }

    /**
     * API: 申請をキャンセル
     */
    public function apiCancelRequest($params)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }

        $userId = $this->auth->id();
        $success = $this->model->cancelRequest($id, $userId);
        
        if (!$success) {
            return ['error' => 'Failed to cancel request or permission denied', 'code' => 400];
        }

        return [
            'success' => true,
            'message' => '申請をキャンセルしました',
            'redirect' => BASE_PATH . '/workflow/requests'
        ];
    }

    /**
     * API: コメントを追加
     */
    public function apiAddComment($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $requestId = $params['id'] ?? null;
        if (!$requestId) {
            return ['error' => 'Invalid request ID', 'code' => 400];
        }

        // 申請の存在チェック
        $request = $this->model->getRequestById($requestId);
        if (!$request) {
            return ['error' => 'Request not found', 'code' => 404];
        }

        // バリデーション
        if (empty($data['comment'])) {
            return ['error' => 'Comment is required', 'code' => 400];
        }

        $userId = $this->auth->id();
        $success = $this->model->addComment($requestId, $userId, $data['comment']);
        
        if (!$success) {
            return ['error' => 'Failed to add comment', 'code' => 500];
        }

        // 最新のコメント一覧を取得
        $comments = $this->model->getComments($requestId);

        return [
            'success' => true,
            'data' => [
                'comments' => $comments
            ],
            'message' => 'コメントを追加しました'
        ];
    }

    /**
     * API: 代理承認設定を追加
     */
    public function apiAddDelegation($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // バリデーション
        if (empty($data['delegate_id']) || empty($data['start_date']) || empty($data['end_date'])) {
            return ['error' => 'Delegate, start date and end date are required', 'code' => 400];
        }

        // 自分自身を代理人にはできない
        $userId = $this->auth->id();
        if ($userId == $data['delegate_id']) {
            return ['error' => 'Cannot delegate to yourself', 'code' => 400];
        }

        // 日付の妥当性チェック
        $startDate = strtotime($data['start_date']);
        $endDate = strtotime($data['end_date']);
        
        if ($startDate > $endDate) {
            return ['error' => 'End date must be after start date', 'code' => 400];
        }

        // データにユーザーIDを追加
        $data['user_id'] = $userId;

        $success = $this->model->addDelegation($data);
        
        if (!$success) {
            return ['error' => 'Failed to add delegation', 'code' => 500];
        }

        return [
            'success' => true,
            'message' => '代理承認設定を追加しました',
            'redirect' => BASE_PATH . '/workflow/delegates'
        ];
    }

    /**
     * API: PDFエクスポート
     */
    public function apiExportPdf($params)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }

        // 申請データを取得
        $exportData = $this->model->getRequestExportData($id);
        if (!$exportData) {
            return ['error' => 'Request not found', 'code' => 404];
        }

        // 権限チェック（管理者、申請者、承認者のみエクスポート可能）
        $userId = $this->auth->id();
        $isAdmin = $this->auth->isAdmin();
        $isRequester = ($exportData['request']['requester_id'] == $userId);
        
        // 承認者かどうかチェック
        $sql = "SELECT COUNT(*) as count FROM workflow_approvals 
                WHERE request_id = ? AND approver_id = ?";
        $isApprover = $this->db->fetch($sql, [$id, $userId])['count'] > 0;
        
        if (!$isAdmin && !$isRequester && !$isApprover) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        // CSVエクスポート処理はここで実装
        // 実装例: フォームデータを CSV 形式に変換

        return [
            'success' => true,
            'data' => [
                'download_url' => BASE_PATH . '/exports/workflow/request_' . $id . '.csv'
            ],
            'message' => 'CSVのエクスポートが完了しました'
        ];
    }


    /**
     * API: CSVエクスポート
     */
    public function apiExportCsv($params)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }

        // 申請データを取得
        $exportData = $this->model->getRequestExportData($id);
        if (!$exportData) {
            return ['error' => 'Request not found', 'code' => 404];
        }


        // 権限チェック（管理者、申請者、承認者のみエクスポート可能）
        $userId = $this->auth->id();
        $isAdmin = $this->auth->isAdmin();
        $isRequester = ($exportData['request']['requester_id'] == $userId);
        
        // 承認者かどうかチェック
        $sql = "SELECT COUNT(*) as count FROM workflow_approvals 
                WHERE request_id = ? AND approver_id = ?";
        $isApprover = $this->db->fetch($sql, [$id, $userId])['count'] > 0;
        
        if (!$isAdmin && !$isRequester && !$isApprover) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        // CSVエクスポート処理はここで実装
        // 実装例: フォームデータを CSV 形式に変換

        return [
            'success' => true,
            'data' => [
                'download_url' => BASE_PATH . '/exports/workflow/request_' . $id . '.csv'
            ],
            'message' => 'CSVのエクスポートが完了しました'
        ];
    }

    /**
     * ファイルアップロード処理
     */
    private function processRequestFiles($files)
    {
        $processedFiles = [];
        $uploadDir = __DIR__ . '/../uploads/workflow/';
        
        // アップロードディレクトリが存在しない場合は作成
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($files as $fieldId => $fileInfo) {
            // 単一ファイルの場合
            if (is_string($fileInfo['name'])) {
                $fileName = $fileInfo['name'];
                $tmpName = $fileInfo['tmp_name'];
                $fileSize = $fileInfo['size'];
                $fileType = $fileInfo['type'];
                
                // 一意のファイル名を生成
                $uniqueName = uniqid() . '_' . $fileName;
                $filePath = $uploadDir . $uniqueName;
                
                // ファイルを移動
                if (move_uploaded_file($tmpName, $filePath)) {
                    $processedFiles[$fieldId] = [
                        'name' => $fileName,
                        'path' => 'uploads/workflow/' . $uniqueName,
                        'size' => $fileSize,
                        'type' => $fileType
                    ];
                }
            }
            // 複数ファイルの場合
            elseif (is_array($fileInfo['name'])) {
                $processedFiles[$fieldId] = [];
                
                for ($i = 0; $i < count($fileInfo['name']); $i++) {
                    $fileName = $fileInfo['name'][$i];
                    $tmpName = $fileInfo['tmp_name'][$i];
                    $fileSize = $fileInfo['size'][$i];
                    $fileType = $fileInfo['type'][$i];
                    
                    // 一意のファイル名を生成
                    $uniqueName = uniqid() . '_' . $fileName;
                    $filePath = $uploadDir . $uniqueName;
                    
                    // ファイルを移動
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $processedFiles[$fieldId][] = [
                            'name' => $fileName,
                            'path' => 'uploads/workflow/' . $uniqueName,
                            'size' => $fileSize,
                            'type' => $fileType
                        ];
                    }
                }
            }
        }
        
        return $processedFiles;
    }
    /**
     * API: 申請一覧を取得
     */
    public function apiGetRequests($params)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // フィルタリング条件
        $filters = [
            'status' => $params['status'] ?? null,
            'template_id' => $params['template_id'] ?? null,
            'search' => $params['search'] ?? null,
        ];

        // ページネーション
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        // 自分が作成した申請のみ表示（管理者以外）
        if (!$this->auth->isAdmin()) {
            $filters['requester_id'] = $this->auth->id();
        }

        // 申請リストを取得
        $requests = $this->model->getRequests($filters, $page, $limit);
        $totalRequests = $this->model->getRequestCount($filters);
        $totalPages = ceil($totalRequests / $limit);

        return [
            'success' => true,
            'data' => [
                'requests' => $requests,
                'pagination' => [
                    'total' => $totalRequests,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'limit' => $limit
                ]
            ]
        ];
    }

    /**
     * API: ワークフロー統計情報を取得
     */
    public function apiGetStats()
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $userId = $this->auth->id();
        $isAdmin = $this->auth->isAdmin();

        // テンプレート数
        $templateCount = $this->model->getTemplateCount();

        // 申請数（管理者は全て、一般ユーザーは自分のみ）
        $requestFilters = $isAdmin ? [] : ['requester_id' => $userId];
        $requestCount = $this->model->getRequestCount($requestFilters);

        // 承認待ち数
        $pendingFilters = [
            'pending_approval' => true,
            'user_id' => $userId
        ];
        $pendingCount = $this->model->getRequestCount($pendingFilters);

        // 自分の申請数
        $myRequestCount = $this->model->getRequestCount(['requester_id' => $userId]);

        // ステータス別統計
        $statusStats = [];
        $statuses = ['draft', 'pending', 'approved', 'rejected', 'cancelled'];

        foreach ($statuses as $status) {
            $filters = ['status' => $status];
            if (!$isAdmin) {
                $filters['requester_id'] = $userId;
            }
            $statusStats[$status] = $this->model->getRequestCount($filters);
        }

        // 最近の申請
        $recentFilters = $isAdmin ? [] : ['requester_id' => $userId];
        $recentRequests = $this->model->getRequests($recentFilters, 1, 5);

        return [
            'success' => true,
            'data' => [
                'templates_count' => $templateCount,
                'requests_count' => $requestCount,
                'pending_approvals' => $pendingCount,
                'my_requests' => $myRequestCount,
                'status_stats' => $statusStats,
                'recent_requests' => $recentRequests
            ]
        ];
    }

    /**
     * API: フォーム定義を一括保存
     */
    public function apiSaveFormDefinitions($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // 権限チェック
        if (!$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        $templateId = $params['id'] ?? null;
        if (!$templateId) {
            return ['error' => 'Invalid template ID', 'code' => 400];
        }

        // テンプレートの存在チェック
        $template = $this->model->getTemplateById($templateId);
        if (!$template) {
            return ['error' => 'Template not found', 'code' => 404];
        }

        // フォーム定義のバリデーション
        if (!isset($data['form_definitions']) || !is_array($data['form_definitions'])) {
            return ['error' => 'Form definitions are required', 'code' => 400];
        }

        // 既存のフォーム定義を取得
        $existingFields = $this->model->getFormDefinitions($templateId);
        $existingFieldIds = array_column($existingFields, 'id');

        // トランザクション開始
        $this->db->beginTransaction();

        try {
            // 新しいフィールドの場合は追加、既存フィールドの場合は更新
            foreach ($data['form_definitions'] as $field) {
                if (empty($field['id']) || !in_array($field['id'], $existingFieldIds)) {
                    // 新規フィールド
                    $field['template_id'] = $templateId;
                    $this->model->addFormField($field);
                } else {
                    // 既存フィールド
                    $this->model->updateFormField($field['id'], $field);
                }
            }

            // 削除されたフィールドを特定して削除
            $newFieldIds = array_filter(array_column($data['form_definitions'], 'id'));
            foreach ($existingFieldIds as $existingId) {
                if (!in_array($existingId, $newFieldIds)) {
                    $this->model->deleteFormField($existingId);
                }
            }

            $this->db->commit();

            // 更新されたフォーム定義を取得
            $formDefinitions = $this->model->getFormDefinitions($templateId);

            return [
                'success' => true,
                'data' => [
                    'form_definitions' => $formDefinitions
                ],
                'message' => 'フォーム定義を保存しました'
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'error' => 'Failed to save form definitions: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * API: 承認経路定義を一括保存
     */
    public function apiSaveRouteDefinitions($params, $data)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        // 権限チェック
        if (!$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        $templateId = $params['id'] ?? null;
        if (!$templateId) {
            return ['error' => 'Invalid template ID', 'code' => 400];
        }

        // テンプレートの存在チェック
        $template = $this->model->getTemplateById($templateId);
        if (!$template) {
            return ['error' => 'Template not found', 'code' => 404];
        }

        // 承認経路定義のバリデーション
        if (!isset($data['route_definitions']) || !is_array($data['route_definitions'])) {
            return ['error' => 'Route definitions are required', 'code' => 400];
        }

        // 既存の承認経路定義を取得
        $existingSteps = $this->model->getRouteDefinitions($templateId);
        $existingStepIds = array_column($existingSteps, 'id');

        // トランザクション開始
        $this->db->beginTransaction();

        try {
            // 新しいステップの場合は追加、既存ステップの場合は更新
            foreach ($data['route_definitions'] as $step) {
                if (empty($step['id']) || !in_array($step['id'], $existingStepIds)) {
                    // 新規ステップ
                    $step['template_id'] = $templateId;
                    $this->model->addRouteStep($step);
                } else {
                    // 既存ステップ
                    $this->model->updateRouteStep($step['id'], $step);
                }
            }

            // 削除されたステップを特定して削除
            $newStepIds = array_filter(array_column($data['route_definitions'], 'id'));
            foreach ($existingStepIds as $existingId) {
                if (!in_array($existingId, $newStepIds)) {
                    $this->model->deleteRouteStep($existingId);
                }
            }

            $this->db->commit();

            // 更新された承認経路定義を取得
            $routeDefinitions = $this->model->getRouteDefinitions($templateId);

            return [
                'success' => true,
                'data' => [
                    'route_definitions' => $routeDefinitions
                ],
                'message' => '承認経路定義を保存しました'
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return [
                'error' => 'Failed to save route definitions: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * API: 個別の申請を取得
     */
    public function apiGetRequest($params)
    {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }

        // 申請の存在チェック
        $request = $this->model->getRequestById($id);
        if (!$request) {
            return ['error' => 'Request not found', 'code' => 404];
        }

        // 権限チェック（管理者、申請者、承認者のみ閲覧可能）
        $userId = $this->auth->id();
        $isAdmin = $this->auth->isAdmin();
        $isRequester = ($request['requester_id'] == $userId);

        // 承認者かどうかチェック
        $sql = "SELECT COUNT(*) as count FROM workflow_approvals 
            WHERE request_id = ? AND approver_id = ?";
        $isApprover = $this->db->fetch($sql, [$id, $userId])['count'] > 0;

        if (!$isAdmin && !$isRequester && !$isApprover) {
            return ['error' => 'Permission denied', 'code' => 403];
        }

        // フォーム定義を取得
        $formDefinitions = $this->model->getFormDefinitions($request['template_id']);

        // フォームデータを取得
        $formData = $this->model->getRequestData($id);

        // 添付ファイルを取得
        $attachments = $this->model->getRequestAttachments($id);

        // 承認履歴を取得
        $approvals = $this->model->getRequestApprovals($id);

        // コメントを取得
        $comments = $this->model->getComments($id);

        // 現在のユーザーの承認タスクを取得
        $currentApproval = null;
        if (!$isAdmin && !$isRequester) {
            $sql = "SELECT * FROM workflow_approvals 
                WHERE request_id = ? AND approver_id = ? AND status = 'pending' 
                AND step_number = ? LIMIT 1";
            $currentApproval = $this->db->fetch($sql, [
                $id,
                $userId,
                $request['current_step']
            ]);
        }

        return [
            'success' => true,
            'data' => [
                'request' => $request,
                'form_definitions' => $formDefinitions,
                'form_data' => $formData,
                'attachments' => $attachments,
                'approvals' => $approvals,
                'comments' => $comments,
                'current_approval' => $currentApproval,
                'is_admin' => $isAdmin,
                'is_requester' => $isRequester,
                'is_approver' => $isApprover
            ]
        ];
    }
}
