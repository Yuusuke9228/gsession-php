<?php
// controllers/ScheduleController.php
namespace Controllers;

use Core\Auth;
use Models\Schedule;
use Models\User;
use Models\Organization;

class ScheduleController {
    private $auth;
    private $model;
    
    public function __construct() {
        $this->auth = Auth::getInstance();
        $this->model = new Schedule();
    }
    
    // カレンダーページを表示（日表示）
    public function day() {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        // 日付パラメータ（未指定時は今日）
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // 表示ユーザーID（未指定時は自分）
        $userId = $_GET['user_id'] ?? $this->auth->id();
        
        // 日付の妥当性チェック
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        
        // スケジュールデータ取得
        $schedules = $this->model->getUserDaySchedules($userId, $date);
        
        // ユーザー情報取得
        $userModel = new User();
        $user = $userModel->getById($userId);
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule/day.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // カレンダーページを表示（週表示）
    public function week() {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        // 日付パラメータ（未指定時は今日）
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // 表示ユーザーID（未指定時は自分）
        $userId = $_GET['user_id'] ?? $this->auth->id();
        
        // 日付の妥当性チェック
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        
        // スケジュールデータ取得
        $schedules = $this->model->getUserWeekSchedules($userId, $date);
        
        // 週の日付範囲を計算
        $dateObj = new \DateTime($date);
        $weekday = $dateObj->format('w'); // 0（日曜）から6（土曜）
        $dateObj->modify('-' . $weekday . ' days'); // 週の開始日（日曜）
        $weekDates = [];
        
        for ($i = 0; $i < 7; $i++) {
            $currentDate = clone $dateObj;
            $currentDate->modify('+' . $i . ' days');
            $weekDates[] = $currentDate->format('Y-m-d');
        }
        
        // ユーザー情報取得
        $userModel = new User();
        $user = $userModel->getById($userId);
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule/week.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // カレンダーページを表示（月表示）
    public function month() {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        // 年月パラメータ（未指定時は今月）
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        // 表示ユーザーID（未指定時は自分）
        $userId = $_GET['user_id'] ?? $this->auth->id();
        
        // 年月の妥当性チェック
        if (!is_numeric($year) || !is_numeric($month) || $month < 1 || $month > 12) {
            $year = date('Y');
            $month = date('m');
        }
        
        // スケジュールデータ取得
        $schedules = $this->model->getUserMonthSchedules($userId, $year, $month);
        
        // 月の日数
        $daysInMonth = date('t', strtotime("$year-$month-01"));
        
        // 月の初日の曜日（0:日曜, 1:月曜, ..., 6:土曜）
        $firstDayOfWeek = date('w', strtotime("$year-$month-01"));
        
        // ユーザー情報取得
        $userModel = new User();
        $user = $userModel->getById($userId);
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule/month.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // スケジュール作成ページを表示
    public function create() {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        // 初期日時（パラメータから取得、未指定時は現在）
        $date = $_GET['date'] ?? date('Y-m-d');
        $time = $_GET['time'] ?? date('H:00');
        
        // ユーザーリスト取得（参加者選択用）
        $userModel = new User();
        $users = $userModel->getAll(1, 1000); // 最大1000人
        
        // 組織リスト取得（共有用）
        $orgModel = new Organization();
        $organizations = $orgModel->getAll();
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule/create.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // スケジュール編集ページを表示
    public function edit($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            header('Location:' . BASE_PATH . '/schedule');
            exit;
        }
        
        // スケジュール情報を取得
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            header('Location:' . BASE_PATH . '/schedule');
            exit;
        }
        
        // 作成者かどうかチェック
        if ($schedule['creator_id'] != $this->auth->id() && !$this->auth->isAdmin()) {
            header('Location:' . BASE_PATH . '/schedule/view/' . $id);
            exit;
        }
        
        // 参加者リスト取得
        $participants = $this->model->getParticipants($id);
        $participantIds = array_column($participants, 'id');
        
        // 共有組織リスト取得
        $sharedOrganizations = $this->model->getOrganizations($id);
        $sharedOrgIds = array_column($sharedOrganizations, 'id');
        
        // ユーザーリスト取得（参加者選択用）
        $userModel = new User();
        $users = $userModel->getAll(1, 1000); // 最大1000人
        
        // 組織リスト取得（共有用）
        $orgModel = new Organization();
        $organizations = $orgModel->getAll();
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule/edit.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // スケジュール詳細ページを表示
    public function view($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            header('Location:' . BASE_PATH . '/login');
            exit;
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            header('Location:' . BASE_PATH . '/schedule');
            exit;
        }
        
        // スケジュール情報を取得
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            header('Location:' . BASE_PATH . '/schedule');
            exit;
        }
        
        // 参加者リスト取得
        $participants = $this->model->getParticipants($id);
        
        // 共有組織リスト取得
        $sharedOrganizations = $this->model->getOrganizations($id);
        
        // 自分が参加者かどうか
        $isParticipant = false;
        $participationStatus = null;
        foreach ($participants as $participant) {
            if ($participant['id'] == $this->auth->id()) {
                $isParticipant = true;
                $participationStatus = $participant['participation_status'];
                break;
            }
        }
        
        // ビューを読み込む
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/schedule/view.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
    
    // API: 日単位スケジュールを取得
    public function apiGetDay($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // 日付
        $date = $params['date'] ?? date('Y-m-d');
        
        // ユーザーID
        $userId = $params['user_id'] ?? $this->auth->id();
        
        $schedules = $this->model->getUserDaySchedules($userId, $date);
        
        return ['success' => true, 'data' => $schedules];
    }
    
    // API: 週単位スケジュールを取得
    public function apiGetWeek($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // 日付
        $date = $params['date'] ?? date('Y-m-d');
        
        // ユーザーID
        $userId = $params['user_id'] ?? $this->auth->id();
        
        $schedules = $this->model->getUserWeekSchedules($userId, $date);
        
        // 週の日付範囲を計算
        $dateObj = new \DateTime($date);
        $weekday = $dateObj->format('w'); // 0（日曜）から6（土曜）
        $dateObj->modify('-' . $weekday . ' days'); // 週の開始日（日曜）
        $weekDates = [];
        
        for ($i = 0; $i < 7; $i++) {
            $currentDate = clone $dateObj;
            $currentDate->modify('+' . $i . ' days');
            $weekDates[] = $currentDate->format('Y-m-d');
        }
        
        return [
            'success' => true,
            'data' => [
                'schedules' => $schedules,
                'week_dates' => $weekDates
            ]
        ];
    }
    
    // API: 月単位スケジュールを取得
    public function apiGetMonth($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // 年月
        $year = $params['year'] ?? date('Y');
        $month = $params['month'] ?? date('m');
        
        // ユーザーID
        $userId = $params['user_id'] ?? $this->auth->id();
        
        $schedules = $this->model->getUserMonthSchedules($userId, $year, $month);
        
        // 月の日数と初日の曜日
        $daysInMonth = date('t', strtotime("$year-$month-01"));
        $firstDayOfWeek = date('w', strtotime("$year-$month-01"));
        
        return [
            'success' => true,
            'data' => [
                'schedules' => $schedules,
                'days_in_month' => $daysInMonth,
                'first_day_of_week' => $firstDayOfWeek
            ]
        ];
    }
    
    // API: 特定の期間のスケジュールを取得
    public function apiGetByDateRange($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // 開始日時と終了日時
        $startDate = $params['start_date'] ?? date('Y-m-d');
        $endDate = $params['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
        
        // ユーザーID
        $userId = $params['user_id'] ?? $this->auth->id();
        
        // 組織ID
        $organizationId = $params['organization_id'] ?? null;
        
        $schedules = $this->model->getByDateRange($startDate, $endDate, $userId, $organizationId);
        
        return ['success' => true, 'data' => $schedules];
    }
    
    // API: 特定のスケジュールを取得
    public function apiGetOne($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 参加者リスト取得
        $participants = $this->model->getParticipants($id);
        
        // 共有組織リスト取得
        $sharedOrganizations = $this->model->getOrganizations($id);
        
        return [
            'success' => true,
            'data' => [
                'schedule' => $schedule,
                'participants' => $participants,
                'organizations' => $sharedOrganizations
            ]
        ];
    }
    
    // API: スケジュールを作成
    public function apiCreate($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        // 作成者IDを設定
        $data['creator_id'] = $this->auth->id();
        
        // バリデーション
        if (empty($data['title']) || empty($data['start_time']) || empty($data['end_time'])) {
            return ['error' => 'Title, start time and end time are required', 'code' => 400];
        }
        
        // 開始時間と終了時間の整合性チェック
        if (strtotime($data['start_time']) > strtotime($data['end_time'])) {
            return ['error' => 'Start time must be before end time', 'code' => 400];
        }
        
        $id = $this->model->create($data);
        if (!$id) {
            return ['error' => 'Failed to create schedule', 'code' => 500];
        }
        
        $schedule = $this->model->getById($id);
        
        return ['success' => true, 'data' => $schedule, 'message' => 'スケジュールを作成しました'];
    }
    
    // API: スケジュールを更新
    public function apiUpdate($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        // スケジュールの存在チェック
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 作成者または管理者のみ更新可能
        if ($schedule['creator_id'] != $this->auth->id() && !$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        // バリデーション
        if (isset($data['start_time']) && isset($data['end_time']) && 
            strtotime($data['start_time']) > strtotime($data['end_time'])) {
            return ['error' => 'Start time must be before end time', 'code' => 400];
        }
        
        $success = $this->model->update($id, $data, $this->auth->id());
        if (!$success) {
            return ['error' => 'Failed to update schedule', 'code' => 500];
        }
        
        $schedule = $this->model->getById($id);
        
        return ['success' => true, 'data' => $schedule, 'message' => 'スケジュールを更新しました'];
    }
    
    // API: スケジュールを削除
    public function apiDelete($params) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id) {
            return ['error' => 'Invalid ID', 'code' => 400];
        }
        
        // スケジュールの存在チェック
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 作成者または管理者のみ削除可能
        if ($schedule['creator_id'] != $this->auth->id() && !$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        $success = $this->model->delete($id, $this->auth->id());
        if (!$success) {
            return ['error' => 'Failed to delete schedule', 'code' => 500];
        }
        
        return ['success' => true, 'message' => 'スケジュールを削除しました'];
    }
    
    // API: 参加ステータスを更新
    public function apiUpdateParticipantStatus($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id || empty($data['status'])) {
            return ['error' => 'Invalid parameters', 'code' => 400];
        }
        
        // スケジュールの存在チェック
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 参加者リスト確認
        $participants = $this->model->getParticipants($id);
        $isParticipant = false;
        
        foreach ($participants as $participant) {
            if ($participant['id'] == $this->auth->id()) {
                $isParticipant = true;
                break;
            }
        }
        
        if (!$isParticipant) {
            return ['error' => 'You are not a participant of this schedule', 'code' => 403];
        }
        
        // ステータス値のバリデーション
        $allowedStatuses = ['pending', 'accepted', 'declined', 'tentative'];
        if (!in_array($data['status'], $allowedStatuses)) {
            return ['error' => 'Invalid status value', 'code' => 400];
        }
        
        $success = $this->model->updateParticipantStatus($id, $this->auth->id(), $data['status']);
        if (!$success) {
            return ['error' => 'Failed to update status', 'code' => 500];
        }
        
        return ['success' => true, 'message' => '参加ステータスを更新しました'];
    }
    
    // API: 参加者を追加
    public function apiAddParticipant($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id || empty($data['user_id'])) {
            return ['error' => 'Invalid parameters', 'code' => 400];
        }
        
        // スケジュールの存在チェック
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 作成者または管理者のみ参加者追加可能
        if ($schedule['creator_id'] != $this->auth->id() && !$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        // ユーザーの存在チェック
        $userModel = new User();
        $user = $userModel->getById($data['user_id']);
        if (!$user) {
            return ['error' => 'User not found', 'code' => 404];
        }
        
        $success = $this->model->addParticipant($id, $data['user_id'], $data['status'] ?? 'pending');
        if (!$success) {
            return ['error' => 'Failed to add participant', 'code' => 500];
        }
        
        return ['success' => true, 'message' => '参加者を追加しました'];
    }
    
    // API: 参加者を削除
    public function apiRemoveParticipant($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id || empty($data['user_id'])) {
            return ['error' => 'Invalid parameters', 'code' => 400];
        }
        
        // スケジュールの存在チェック
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 作成者、管理者、または自分自身の参加を取り消す場合のみ許可
        if ($schedule['creator_id'] != $this->auth->id() && 
            !$this->auth->isAdmin() && 
            $this->auth->id() != $data['user_id']) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        $success = $this->model->removeParticipant($id, $data['user_id']);
        if (!$success) {
            return ['error' => 'Failed to remove participant', 'code' => 500];
        }
        
        return ['success' => true, 'message' => '参加者を削除しました'];
    }
    
    // API: 組織を共有対象に追加
    public function apiAddOrganization($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id || empty($data['organization_id'])) {
            return ['error' => 'Invalid parameters', 'code' => 400];
        }
        
        // スケジュールの存在チェック
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 作成者または管理者のみ組織共有可能
        if ($schedule['creator_id'] != $this->auth->id() && !$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        // 組織の存在チェック
        $orgModel = new Organization();
        $org = $orgModel->getById($data['organization_id']);
        if (!$org) {
            return ['error' => 'Organization not found', 'code' => 404];
        }
        
        $success = $this->model->addOrganization($id, $data['organization_id']);
        if (!$success) {
            return ['error' => 'Failed to add organization', 'code' => 500];
        }
        
        return ['success' => true, 'message' => '組織を共有対象に追加しました'];
    }
    
    // API: 組織を共有対象から削除
    public function apiRemoveOrganization($params, $data) {
        // 認証チェック
        if (!$this->auth->check()) {
            return ['error' => 'Unauthorized', 'code' => 401];
        }
        
        $id = $params['id'] ?? null;
        if (!$id || empty($data['organization_id'])) {
            return ['error' => 'Invalid parameters', 'code' => 400];
        }
        
        // スケジュールの存在チェック
        $schedule = $this->model->getById($id, $this->auth->id());
        if (!$schedule) {
            return ['error' => 'Schedule not found or access denied', 'code' => 404];
        }
        
        // 作成者または管理者のみ組織共有削除可能
        if ($schedule['creator_id'] != $this->auth->id() && !$this->auth->isAdmin()) {
            return ['error' => 'Permission denied', 'code' => 403];
        }
        
        $success = $this->model->removeOrganization($id, $data['organization_id']);
        if (!$success) {
            return ['error' => 'Failed to remove organization', 'code' => 500];
        }
        
        return ['success' => true, 'message' => '組織を共有対象から削除しました'];
    }
}