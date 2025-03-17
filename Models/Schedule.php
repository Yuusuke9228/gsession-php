<?php
// models/Schedule.php
namespace Models;

use Core\Database;

class Schedule
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // 特定の期間のスケジュールを取得
    public function getByDateRange($startDate, $endDate, $userId = null, $organizationId = null)
    {
        $params = [$startDate, $endDate];

        $sql = "SELECT s.*, 
                    u.display_name as creator_name, 
                    COUNT(sp.user_id) as participant_count 
                FROM schedules s 
                LEFT JOIN users u ON s.creator_id = u.id 
                LEFT JOIN schedule_participants sp ON s.id = sp.schedule_id ";

        $whereClauses = [];
        $whereClauses[] = "(s.start_time <= ? AND s.end_time >= ?)";
        $params[] = $endDate;
        $params[] = $startDate;

        // 特定のユーザー向けのフィルタリング
        if ($userId) {
            $sql .= "LEFT JOIN schedule_participants sp2 ON s.id = sp2.schedule_id AND sp2.user_id = ? ";
            $params[] = $userId;

            $whereClauses[] = "(s.creator_id = ? OR sp2.user_id IS NOT NULL OR s.visibility = 'public'";
            $params[] = $userId;

            // 組織共有されたスケジュール
            if ($organizationId) {
                $whereClauses[count($whereClauses) - 1] .= " OR s.id IN (
                    SELECT schedule_id FROM schedule_organizations 
                    WHERE organization_id IN (
                        SELECT organization_id FROM user_organizations 
                        WHERE user_id = ?
                    )
                )";
                $params[] = $userId;
            }

            $whereClauses[count($whereClauses) - 1] .= ")";
        }
        // 特定の組織向けのフィルタリング
        else if ($organizationId) {
            $whereClauses[] = "(s.id IN (
                SELECT schedule_id FROM schedule_organizations 
                WHERE organization_id = ?
            ) OR s.visibility = 'public')";
            $params[] = $organizationId;
        }

        if (!empty($whereClauses)) {
            $sql .= "WHERE " . implode(" AND ", $whereClauses) . " ";
        }

        $sql .= "GROUP BY s.id ORDER BY s.start_time";

        return $this->db->fetchAll($sql, $params);
    }

    // 特定のスケジュールを取得
    public function getById($id, $userId = null)
    {
        $params = [$id];

        $sql = "SELECT s.*, 
                    u.display_name as creator_name 
                FROM schedules s 
                LEFT JOIN users u ON s.creator_id = u.id ";

        // 特定のユーザー向けのアクセス制御
        if ($userId) {
            $sql .= "LEFT JOIN schedule_participants sp ON s.id = sp.schedule_id AND sp.user_id = ? ";
            $params[] = $userId;

            $sql .= "WHERE s.id = ? AND (s.creator_id = ? OR sp.user_id IS NOT NULL OR s.visibility = 'public' OR s.id IN (
                SELECT schedule_id FROM schedule_organizations so
                JOIN user_organizations uo ON so.organization_id = uo.organization_id
                WHERE uo.user_id = ?
            )) ";
            $params[] = $id;
            $params[] = $userId;
            $params[] = $userId;
        } else {
            $sql .= "WHERE s.id = ? ";
        }

        $sql .= "LIMIT 1";

        return $this->db->fetch($sql, $params);
    }

    // スケジュールの参加者を取得
    public function getParticipants($scheduleId)
    {
        $sql = "SELECT u.*, sp.status as participation_status 
                FROM schedule_participants sp 
                JOIN users u ON sp.user_id = u.id 
                WHERE sp.schedule_id = ? 
                ORDER BY u.display_name";

        return $this->db->fetchAll($sql, [$scheduleId]);
    }

    // スケジュールの共有組織を取得
    public function getOrganizations($scheduleId)
    {
        $sql = "SELECT o.* 
                FROM schedule_organizations so 
                JOIN organizations o ON so.organization_id = o.id 
                WHERE so.schedule_id = ? 
                ORDER BY o.name";

        return $this->db->fetchAll($sql, [$scheduleId]);
    }

    // スケジュールを作成
    public function create($data)
    {
        // 必須項目チェック
        if (
            empty($data['title']) || empty($data['start_time']) ||
            empty($data['end_time']) || empty($data['creator_id'])
        ) {
            return false;
        }

        // 開始時間と終了時間の整合性チェック
        if (strtotime($data['start_time']) > strtotime($data['end_time'])) {
            return false;
        }

        // トランザクション開始
        $this->db->beginTransaction();

        try {
            // スケジュール情報を挿入
            $sql = "INSERT INTO schedules (
                        title, 
                        description, 
                        start_time, 
                        end_time, 
                        all_day, 
                        location, 
                        creator_id, 
                        visibility, 
                        priority, 
                        status, 
                        repeat_type, 
                        repeat_end_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->execute($sql, [
                $data['title'],
                $data['description'] ?? null,
                $data['start_time'],
                $data['end_time'],
                $data['all_day'] ?? 0,
                $data['location'] ?? null,
                $data['creator_id'],
                $data['visibility'] ?? 'public',
                $data['priority'] ?? 'normal',
                $data['status'] ?? 'scheduled',
                $data['repeat_type'] ?? 'none',
                $data['repeat_end_date'] ?? null
            ]);

            $scheduleId = $this->db->lastInsertId();

            // 参加者を追加
            if (!empty($data['participants']) && is_array($data['participants'])) {
                foreach ($data['participants'] as $participant) {
                    $this->addParticipant($scheduleId, $participant);
                }
            }

            // 共有組織を追加
            if (!empty($data['organizations']) && is_array($data['organizations'])) {
                foreach ($data['organizations'] as $orgId) {
                    $this->addOrganization($scheduleId, $orgId);
                }
            }

            // 繰り返しスケジュールの処理
            if (!empty($data['repeat_type']) && $data['repeat_type'] !== 'none' && !empty($data['repeat_end_date'])) {
                $this->createRepeatSchedules($scheduleId, $data);
            }

            $this->db->commit();
            return $scheduleId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // 繰り返しスケジュールを作成
    private function createRepeatSchedules($originalId, $data)
    {
        $original = $this->getById($originalId);
        if (!$original) {
            return false;
        }

        $startTime = new \DateTime($original['start_time']);
        $endTime = new \DateTime($original['end_time']);
        $duration = $startTime->diff($endTime);
        $repeatEndDate = new \DateTime($original['repeat_end_date']);

        $interval = null;
        switch ($original['repeat_type']) {
            case 'daily':
                $interval = new \DateInterval('P1D');
                break;
            case 'weekly':
                $interval = new \DateInterval('P1W');
                break;
            case 'monthly':
                $interval = new \DateInterval('P1M');
                break;
            case 'yearly':
                $interval = new \DateInterval('P1Y');
                break;
            default:
                return false;
        }

        // 最初の繰り返し日を設定
        $currentStart = clone $startTime;
        $currentStart->add($interval);

        // 繰り返し終了日まで処理
        while ($currentStart <= $repeatEndDate) {
            $currentEnd = clone $currentStart;
            $currentEnd->add($duration);

            // 新しいスケジュールデータ
            $newData = [
                'title' => $original['title'],
                'description' => $original['description'],
                'start_time' => $currentStart->format('Y-m-d H:i:s'),
                'end_time' => $currentEnd->format('Y-m-d H:i:s'),
                'all_day' => $original['all_day'],
                'location' => $original['location'],
                'creator_id' => $original['creator_id'],
                'visibility' => $original['visibility'],
                'priority' => $original['priority'],
                'status' => $original['status'],
                'repeat_type' => 'none', // 個別のインスタンスでは繰り返しなし
            ];

            // 参加者を取得
            $participants = $this->getParticipants($originalId);
            if ($participants) {
                $newData['participants'] = array_column($participants, 'id');
            }

            // 共有組織を取得
            $organizations = $this->getOrganizations($originalId);
            if ($organizations) {
                $newData['organizations'] = array_column($organizations, 'id');
            }

            // 新しいスケジュールを作成
            $this->create($newData);

            // 次の繰り返し日に移動
            $currentStart->add($interval);
        }

        return true;
    }

    // スケジュールを更新
    public function update($id, $data, $userId = null)
    {
        // スケジュールが存在するか確認
        $schedule = $this->getById($id);
        if (!$schedule) {
            return false;
        }

        // アクセス権チェック
        if ($userId && $schedule['creator_id'] != $userId) {
            return false; // 作成者以外は編集不可
        }

        // 開始時間と終了時間の整合性チェック
        if (
            isset($data['start_time']) && isset($data['end_time']) &&
            strtotime($data['start_time']) > strtotime($data['end_time'])
        ) {
            return false;
        }

        // 更新フィールドと値の準備
        $fields = [];
        $values = [];

        // 更新可能なフィールド
        $updateableFields = [
            'title',
            'description',
            'start_time',
            'end_time',
            'all_day',
            'location',
            'visibility',
            'priority',
            'status',
            'repeat_type',
            'repeat_end_date'
        ];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true; // 更新するものがない
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id; // WHEREの条件用

        // トランザクション開始
        $this->db->beginTransaction();

        try {
            // スケジュール情報更新
            $sql = "UPDATE schedules SET " . implode(", ", $fields) . " WHERE id = ?";
            $this->db->execute($sql, $values);

            // 参加者を更新
            if (isset($data['participants']) && is_array($data['participants'])) {
                // 現在の参加者を削除
                $this->db->execute("DELETE FROM schedule_participants WHERE schedule_id = ?", [$id]);

                // 新しい参加者を追加
                foreach ($data['participants'] as $participant) {
                    $this->addParticipant($id, $participant);
                }
            }

            // 共有組織を更新
            if (isset($data['organizations']) && is_array($data['organizations'])) {
                // 現在の共有組織を削除
                $this->db->execute("DELETE FROM schedule_organizations WHERE schedule_id = ?", [$id]);

                // 新しい共有組織を追加
                foreach ($data['organizations'] as $orgId) {
                    $this->addOrganization($id, $orgId);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // スケジュールを削除
    public function delete($id, $userId = null)
    {
        // スケジュールが存在するか確認
        $schedule = $this->getById($id);
        if (!$schedule) {
            return false;
        }

        // アクセス権チェック
        if ($userId && $schedule['creator_id'] != $userId) {
            return false; // 作成者以外は削除不可
        }

        // トランザクション開始
        $this->db->beginTransaction();

        try {
            // 参加者を削除
            $this->db->execute("DELETE FROM schedule_participants WHERE schedule_id = ?", [$id]);

            // 共有組織を削除
            $this->db->execute("DELETE FROM schedule_organizations WHERE schedule_id = ?", [$id]);

            // スケジュールを削除
            $this->db->execute("DELETE FROM schedules WHERE id = ?", [$id]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // 参加者を追加
    public function addParticipant($scheduleId, $userId, $status = 'pending')
    {
        $sql = "INSERT INTO schedule_participants (
                    schedule_id, 
                    user_id, 
                    status
                ) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE status = ?";

        return $this->db->execute($sql, [$scheduleId, $userId, $status, $status]);
    }

    // 参加者のステータスを更新
    public function updateParticipantStatus($scheduleId, $userId, $status)
    {
        $sql = "UPDATE schedule_participants 
                SET status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE schedule_id = ? AND user_id = ?";

        return $this->db->execute($sql, [$status, $scheduleId, $userId]);
    }

    // 参加者を削除
    public function removeParticipant($scheduleId, $userId)
    {
        $sql = "DELETE FROM schedule_participants 
                WHERE schedule_id = ? AND user_id = ?";

        return $this->db->execute($sql, [$scheduleId, $userId]);
    }

    // 組織を追加
    public function addOrganization($scheduleId, $organizationId)
    {
        $sql = "INSERT IGNORE INTO schedule_organizations (
                    schedule_id, 
                    organization_id
                ) VALUES (?, ?)";

        return $this->db->execute($sql, [$scheduleId, $organizationId]);
    }

    // 組織を削除
    public function removeOrganization($scheduleId, $organizationId)
    {
        $sql = "DELETE FROM schedule_organizations 
                WHERE schedule_id = ? AND organization_id = ?";

        return $this->db->execute($sql, [$scheduleId, $organizationId]);
    }

    // ユーザーの特定日のスケジュールを取得
    public function getUserDaySchedules($userId, $date)
    {
        $startOfDay = date('Y-m-d 00:00:00', strtotime($date));
        $endOfDay = date('Y-m-d 23:59:59', strtotime($date));

        return $this->getByDateRange($startOfDay, $endOfDay, $userId);
    }

    // ユーザーの特定週のスケジュールを取得
    public function getUserWeekSchedules($userId, $date)
    {
        $dateObj = new \DateTime($date);
        $weekday = $dateObj->format('w'); // 0（日曜）から6（土曜）

        // 週の開始日（日曜）に調整
        $dateObj->modify('-' . $weekday . ' days');
        $startOfWeek = $dateObj->format('Y-m-d 00:00:00');

        // 週の終了日（土曜）に調整
        $dateObj->modify('+6 days');
        $endOfWeek = $dateObj->format('Y-m-d 23:59:59');

        return $this->getByDateRange($startOfWeek, $endOfWeek, $userId);
    }

    // ユーザーの特定月のスケジュールを取得
    public function getUserMonthSchedules($userId, $year, $month)
    {
        $startOfMonth = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $endOfMonth = date('Y-m-t 23:59:59', strtotime($startOfMonth));

        return $this->getByDateRange($startOfMonth, $endOfMonth, $userId);
    }
}
