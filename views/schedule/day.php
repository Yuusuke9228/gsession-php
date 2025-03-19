<?php
// views/schedule/day.php
$pageTitle = 'スケジュール（日表示） - GroupWare';

// 日付フォーマット
$formattedDate = date('Y年n月j日', strtotime($date));
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($date))];
?>
<?php include __DIR__ . '/modal.php'; ?>
<div class="container-fluid" data-page-type="day">
    <input type="hidden" id="current-date" value="<?php echo $date; ?>">
    <input type="hidden" id="user-id" value="<?php echo $userId; ?>">
    <input type="hidden" id="current-user-id" value="<?php echo $this->auth->id(); ?>">

    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3"><?php echo $formattedDate; ?> (<?php echo $dayOfWeek; ?>) - スケジュール管理</h1>
        </div>
        <div class="col-auto">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary btn-prev-day">
                    <i class="fas fa-chevron-left"></i> 前日
                </button>
                <button type="button" class="btn btn-outline-secondary btn-today">
                    今日
                </button>
                <button type="button" class="btn btn-outline-secondary btn-next-day">
                    翌日 <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="col-auto">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-primary btn-view-switcher" data-view="day">
                    <i class="fas fa-calendar-day"></i> 日
                </button>
                <button type="button" class="btn btn-outline-primary btn-view-switcher" data-view="week">
                    <i class="fas fa-calendar-week"></i> 週
                </button>
                <button type="button" class="btn btn-outline-primary btn-view-switcher" data-view="month">
                    <i class="fas fa-calendar-alt"></i> 月
                </button>
            </div>
        </div>
        <div class="col-auto">
            <button id="btn-create-schedule" class="btn btn-success" data-date="<?php echo $date; ?>">
                <i class="fas fa-plus"></i> 新規作成
            </button>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="user-selector" class="visually-hidden">ユーザー選択</label>
            <select id="user-selector" class="form-select">
                <?php if (isset($users) && is_array($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $userId == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="<?php echo $user['id']; ?>" selected>
                        <?php echo htmlspecialchars($user['display_name']); ?>
                    </option>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="day-schedule-container">
                <!-- スケジュールはJSで動的に生成 -->
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* 日表示用スタイル */
    .schedule-timeline {
        display: flex;
        flex-direction: column;
    }

    .schedule-hour {
        display: flex;
        min-height: 60px;
        border-bottom: 1px solid #dee2e6;
    }

    .schedule-time {
        width: 80px;
        padding: 8px;
        font-weight: bold;
        color: #6c757d;
        text-align: right;
        border-right: 1px solid #dee2e6;
    }

    .schedule-items {
        flex: 1;
        padding: 5px;
        min-height: 60px;
    }

    .schedule-item {
        margin-bottom: 5px;
        padding: 5px 8px;
        border-radius: 4px;
        cursor: pointer;
    }

    .schedule-item a {
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .schedule-item .schedule-title {
        font-weight: bold;
    }

    .empty-slot {
        width: 100%;
        height: 100%;
        min-height: 50px;
        cursor: pointer;
    }

    .empty-slot:hover {
        background-color: #f8f9fa;
    }

    .priority-high {
        background-color: #f8d7da;
        border-left: 3px solid #dc3545;
    }

    .priority-normal {
        background-color: #d1e7dd;
        border-left: 3px solid #198754;
    }

    .priority-low {
        background-color: #cfe2ff;
        border-left: 3px solid #0d6efd;
    }
</style>