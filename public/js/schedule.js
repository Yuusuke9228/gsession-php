var BASE_PATH = "/gsession-php/public";

/**
 * GroupSession PHP - スケジュール管理JS
 */

const Schedule = {
    // カレンダーインスタンス
    calendar: null,
    
    // 現在の表示モード（day/week/month）
    currentView: 'month',
    
    // 現在表示中のユーザーID
    currentUserId: null,
    
    // 初期化
    init: function() {
        const page = $('[data-page-type]').data('page-type');
        
        // 現在のユーザーIDを取得
        this.currentUserId = $('#current-user-id').val() || null;
        
        // ページタイプに応じた初期化
        switch (page) {
            case 'day':
                this.initDay();
                break;
            case 'week':
                this.initWeek();
                break;
            case 'month':
                this.initMonth();
                break;
            case 'create':
                this.initForm();
                break;
            case 'edit':
                this.initForm();
                break;
            case 'view':
                this.initView();
                break;
            case 'calendar':
                this.initCalendar();
                break;
        }
        
        // 共通のイベントハンドラ
        this.initCommonHandlers();
    },
    
    // 共通のイベントハンドラを設定
    initCommonHandlers: function() {
        // 新規作成ボタンのイベントハンドラ
        $('#btn-create-schedule').on('click', function() {
            const date = $(this).data('date') || moment().format('YYYY-MM-DD');
            window.location.href = BASE_PATH +'/schedule/create?date=' + date;
        });
        
        // 表示切替ボタン
        $('.btn-view-switcher').on('click', function() {
            const view = $(this).data('view');
            const date = $('#current-date').val() || moment().format('YYYY-MM-DD');
            
            // ビューに応じたURLに遷移
            switch (view) {
                case 'day':
                    window.location.href = BASE_PATH + '/schedule/day?date=' + date;
                    break;
                case 'week':
                    window.location.href = BASE_PATH + '/schedule/week?date=' + date;
                    break;
                case 'month':
                    const monthDate = moment(date, 'YYYY-MM-DD');
                    window.location.href = BASE_PATH + '/schedule/month?year=' + monthDate.year() + '&month=' + (monthDate.month() + 1);
                    break;
            }
        });
        
        // ユーザー切替セレクトボックス
        $('#user-selector').on('change', function() {
            const userId = $(this).val();
            const currentPath = window.location.pathname;
            const urlParams = new URLSearchParams(window.location.search);
            
            urlParams.set('user_id', userId);
            
            window.location.href = currentPath + '?' + urlParams.toString();
        });
    },
    
    // 日表示の初期化
    initDay: function() {
        const date = $('#current-date').val();
        const userId = $('#user-id').val();
        
        // スケジュールデータを取得
        this.loadDaySchedules(date, userId);
        
        // 前日・翌日ボタン
        $('.btn-prev-day').on('click', function() {
            const prevDate = moment(date, 'YYYY-MM-DD').subtract(1, 'days').format('YYYY-MM-DD');
            window.location.href = BASE_PATH + '/schedule/day?date=' + prevDate + '&user_id=' + userId;
        });
        
        $('.btn-next-day').on('click', function() {
            const nextDate = moment(date, 'YYYY-MM-DD').add(1, 'days').format('YYYY-MM-DD');
            window.location.href = BASE_PATH + '/schedule/day?date=' + nextDate + '&user_id=' + userId;
        });
        
        // 今日ボタン
        $('.btn-today').on('click', function() {
            const today = moment().format('YYYY-MM-DD');
            window.location.href = BASE_PATH + '/schedule/day?date=' + today + '&user_id=' + userId;
        });
    },
    
    // 週表示の初期化
    initWeek: function() {
        const date = $('#current-date').val();
        const userId = $('#user-id').val();
        
        // スケジュールデータを取得
        this.loadWeekSchedules(date, userId);
        
        // 前週・翌週ボタン
        $('.btn-prev-week').on('click', function() {
            const prevWeek = moment(date, 'YYYY-MM-DD').subtract(7, 'days').format('YYYY-MM-DD');
            window.location.href = BASE_PATH + '/schedule/week?date=' + prevWeek + '&user_id=' + userId;
        });
        
        $('.btn-next-week').on('click', function() {
            const nextWeek = moment(date, 'YYYY-MM-DD').add(7, 'days').format('YYYY-MM-DD');
            window.location.href = BASE_PATH + '/schedule/week?date=' + nextWeek + '&user_id=' + userId;
        });
        
        // 今週ボタン
        $('.btn-this-week').on('click', function() {
            const today = moment().format('YYYY-MM-DD');
            window.location.href = BASE_PATH + '/schedule/week?date=' + today + '&user_id=' + userId;
        });
    },
    
    // 月表示の初期化
    initMonth: function() {
        const year = $('#current-year').val();
        const month = $('#current-month').val();
        const userId = $('#user-id').val();
        
        // スケジュールデータを取得
        this.loadMonthSchedules(year, month, userId);
        
        // 前月・翌月ボタン
        $('.btn-prev-month').on('click', function() {
            const prevMonth = moment(`${year}-${month}-01`, 'YYYY-MM-DD').subtract(1, 'months');
            window.location.href = BASE_PATH + '/schedule/month?year=' + prevMonth.year() + '&month=' + (prevMonth.month() + 1) + '&user_id=' + userId;
        });
        
        $('.btn-next-month').on('click', function() {
            const nextMonth = moment(`${year}-${month}-01`, 'YYYY-MM-DD').add(1, 'months');
            window.location.href = BASE_PATH + '/schedule/month?year=' + nextMonth.year() + '&month=' + (nextMonth.month() + 1) + '&user_id=' + userId;
        });
        
        // 今月ボタン
        $('.btn-this-month').on('click', function() {
            const today = moment();
            window.location.href = BASE_PATH + '/schedule/month?year=' + today.year() + '&month=' + (today.month() + 1) + '&user_id=' + userId;
        });
        
        // 日付セルのクリックイベント
        $(document).on('click', '.calendar-day', function() {
            const date = $(this).data('date');
            if (date) {
                window.location.href = BASE_PATH + '/schedule/day?date=' + date + '&user_id=' + userId;
            }
        });
    },
    
    // フォームページの初期化
    initForm: function() {
        // 日付時間ピッカーの初期化
        this.initDateTimePickers();
        
        // 参加者セレクトの初期化
        this.initParticipantSelect();
        
        // 組織共有セレクトの初期化
        this.initOrganizationSelect();
        
        // 終日フラグの処理
        $('#all_day').on('change', function() {
            if ($(this).is(':checked')) {
                // 終日の場合は時間部分を非表示にして00:00-23:59に設定
                $('.time-picker').hide();
                $('#start_time_time').val('00:00');
                $('#end_time_time').val('23:59');
            } else {
                // 終日でない場合は時間部分を表示
                $('.time-picker').show();
            }
        });
        
        // 初期表示（チェックボックスの状態に合わせる）
        if ($('#all_day').is(':checked')) {
            $('.time-picker').hide();
        }
        
        // 繰り返し設定の表示切替
        $('#repeat_type').on('change', function() {
            if ($(this).val() !== 'none') {
                $('.repeat-options').show();
            } else {
                $('.repeat-options').hide();
                $('#repeat_end_date').val('');
            }
        });
        
        // 初期表示（繰り返し設定の状態に合わせる）
        if ($('#repeat_type').val() !== 'none') {
            $('.repeat-options').show();
        } else {
            $('.repeat-options').hide();
        }
        
        // 公開範囲の変更処理
        $('#visibility').on('change', function() {
            if ($(this).val() === 'specific') {
                $('.visibility-specific').show();
            } else {
                $('.visibility-specific').hide();
            }
        });
        
        // 初期表示（公開範囲の状態に合わせる）
        if ($('#visibility').val() === 'specific') {
            $('.visibility-specific').show();
        } else {
            $('.visibility-specific').hide();
        }
        
        // フォーム送信前のバリデーション
        $('form').on('submit', function(e) {
            if (!Schedule.validateForm()) {
                e.preventDefault();
            }
            
            // 日付と時間を結合
            const startDate = $('#start_time_date').val();
            const startTime = $('#start_time_time').val();
            $('#start_time').val(startDate + ' ' + startTime);
            
            const endDate = $('#end_time_date').val();
            const endTime = $('#end_time_time').val();
            $('#end_time').val(endDate + ' ' + endTime);
        });
    },
    
    // 詳細ページの初期化
    initView: function() {
        const scheduleId = $('#schedule-id').val();
        
        // 参加ステータス変更ボタン
        $('.btn-participation-status').on('click', function() {
            const status = $(this).data('status');
            Schedule.updateParticipationStatus(scheduleId, status);
        });
    },
    
    // カレンダーページの初期化（FullCalendar使用）
    initCalendar: function() {
        const calendarEl = document.getElementById('calendar');
        
        if (!calendarEl) return;
        
        // FullCalendarの初期化
        this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'ja',
            buttonText: {
                today: '今日',
                month: '月',
                week: '週',
                day: '日'
            },
            navLinks: true,
            editable: false,
            dayMaxEvents: true,
            events: (info, successCallback, failureCallback) => {
                // 指定期間のスケジュールを取得
                const userId = $('#user-id').val();
                
                App.apiGet('/schedule/range', {
                    start_date: moment(info.start).format('YYYY-MM-DD'),
                    end_date: moment(info.end).format('YYYY-MM-DD'),
                    user_id: userId
                })
                .then(response => {
                    if (response.success) {
                        // FullCalendar形式にデータを変換
                        const events = response.data.map(schedule => {
                            return {
                                id: schedule.id,
                                title: schedule.title,
                                start: schedule.start_time,
                                end: schedule.end_time,
                                allDay: schedule.all_day == 1,
                                backgroundColor: this.getPriorityColor(schedule.priority),
                                url: '/schedule/view/' + schedule.id
                            };
                        });
                        
                        successCallback(events);
                    } else {
                        failureCallback(response.error);
                    }
                })
                .catch(error => {
                    failureCallback(error);
                });
            },
            dateClick: (info) => {
                // 日付クリック時のイベント
                window.location.href = BASE_PATH + '/schedule/day?date=' + info.dateStr;
            }
        });
        
        this.calendar.render();
        
        // ユーザー切替時にカレンダーを再読み込み
        $('#user-selector').on('change', function() {
            Schedule.calendar.refetchEvents();
        });
    },
    
    // 日付時間ピッカーの初期化
    initDateTimePickers: function() {
        // flatpickrの初期化
        flatpickr('.date-picker', {
            dateFormat: 'Y-m-d',
            locale: 'ja',
            disableMobile: true
        });
        
        flatpickr('.time-picker', {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            time_24hr: true,
            minuteIncrement: 5,
            disableMobile: true
        });
    },
    
    // 参加者セレクトの初期化
    initParticipantSelect: function() {
        // Select2の初期化
        $('.participant-select').select2({
            placeholder: '参加者を選択してください',
            allowClear: true,
            ajax: {
                url: App.config.apiEndpoint + '/users',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    
                    return {
                        results: data.data.users.map(user => ({
                            id: user.id,
                            text: user.display_name + ' (' + user.username + ')'
                        })),
                        pagination: {
                            more: (params.page * 20) < data.data.pagination.total
                        }
                    };
                },
                cache: true
            },
            templateResult: function(user) {
                if (!user.id) return user.text;
                return $('<span>' + user.text + '</span>');
            }
        });
    },
    
    // 組織共有セレクトの初期化
    initOrganizationSelect: function() {
        // Select2の初期化
        $('.organization-select').select2({
            placeholder: '組織を選択してください',
            allowClear: true,
            ajax: {
                url: App.config.apiEndpoint + '/organizations',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        search: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.data.map(org => ({
                            id: org.id,
                            text: org.name + ' (' + org.code + ')'
                        }))
                    };
                },
                cache: true
            }
        });
    },
    
    // 日単位スケジュールの読み込み
    loadDaySchedules: function(date, userId) {
        App.apiGet('/schedule/day', { date: date, user_id: userId })
            .then(response => {
                if (response.success) {
                    const schedules = response.data;
                    this.renderDaySchedules(schedules, date);
                } else {
                    App.showNotification('スケジュールの読み込みに失敗しました', 'error');
                }
            })
            .catch(error => {
                App.showNotification('スケジュールの読み込みに失敗しました', 'error');
                console.error(error);
            });
    },
    
    // 日単位スケジュールの表示
    renderDaySchedules: function(schedules, date) {
        const container = $('#day-schedule-container');
        
        if (schedules.length === 0) {
            container.html('<div class="alert alert-info">スケジュールはありません</div>');
            return;
        }
        
        // 時間帯ごとにスケジュールを整理
        const timeSlots = {};
        const allDaySchedules = [];
        
        // 時間帯の初期化（1時間ごと）
        for (let hour = 0; hour < 24; hour++) {
            const timeStr = hour.toString().padStart(2, '0') + ':00';
            timeSlots[timeStr] = [];
        }
        
        // スケジュールを時間帯に振り分け
        schedules.forEach(schedule => {
            if (schedule.all_day == 1) {
                allDaySchedules.push(schedule);
            } else {
                const startTime = moment(schedule.start_time).format('HH:mm');
                const hourStr = startTime.substring(0, 2) + ':00';
                
                if (timeSlots[hourStr]) {
                    timeSlots[hourStr].push(schedule);
                }
            }
        });
        
        // HTML生成
        let html = '';
        
        // 終日スケジュール
        if (allDaySchedules.length > 0) {
            html += '<div class="mb-3">';
            html += '<h5 class="mb-2">終日</h5>';
            html += '<div class="list-group">';
            
            allDaySchedules.forEach(schedule => {
                html += this.renderScheduleListItem(schedule);
            });
            
            html += '</div></div>';
        }
        
        // 時間帯ごとのスケジュール
        html += '<div class="schedule-timeline">';
        
        for (let hour = 0; hour < 24; hour++) {
            const timeStr = hour.toString().padStart(2, '0') + ':00';
            const scheduleItems = timeSlots[timeStr];
            
            html += '<div class="schedule-hour">';
            html += '<div class="schedule-time">' + timeStr + '</div>';
            html += '<div class="schedule-items">';
            
            if (scheduleItems.length > 0) {
                scheduleItems.forEach(schedule => {
                    html += this.renderScheduleTimelineItem(schedule);
                });
            } else {
                html += '<div class="empty-slot" data-hour="' + hour + '"></div>';
            }
            
            html += '</div></div>';
        }
        
        html += '</div>';
        
        container.html(html);
        
        // 空のスロットのクリックイベント
        $('.empty-slot').on('click', function() {
            const hour = $(this).data('hour');
            const newDate = moment(date).format('YYYY-MM-DD');
            const newTime = hour.toString().padStart(2, '0') + ':00';
            
            window.location.href = BASE_PATH + '/schedule/create?date=' + newDate + '&time=' + newTime;
        });
    },
    
    // 週単位スケジュールの読み込み
    loadWeekSchedules: function (date, userId) {
        console.log(BASE_PATH);
        App.apiGet(BASE_PATH + '/schedule/week', { date: date, user_id: userId })
            .then(response => {
                if (response.success) {
                    const data = response.data;
                    this.renderWeekSchedules(data.schedules, data.week_dates);
                } else {
                    App.showNotification('スケジュールの読み込みに失敗しました', 'error');
                }
            })
            .catch(error => {
                App.showNotification('スケジュールの読み込みに失敗しました', 'error');
                console.error(error);
            });
    },
    
    // 週単位スケジュールの表示
    renderWeekSchedules: function(schedules, weekDates) {
        const container = $('#week-schedule-container');
        
        // 日付ごとにスケジュールを整理
        const dailySchedules = {};
        weekDates.forEach(date => {
            dailySchedules[date] = {
                allDay: [],
                timeSlots: {}
            };
            
            // 時間帯の初期化（1時間ごと）
            for (let hour = 0; hour < 24; hour++) {
                const timeStr = hour.toString().padStart(2, '0') + ':00';
                dailySchedules[date].timeSlots[timeStr] = [];
            }
        });
        
        // スケジュールを日付と時間帯に振り分け
        schedules.forEach(schedule => {
            const startDate = moment(schedule.start_time).format('YYYY-MM-DD');
            const endDate = moment(schedule.end_time).format('YYYY-MM-DD');
            
            // 複数日にまたがるスケジュールは各日に表示
            for (let d = 0; d < weekDates.length; d++) {
                const currentDate = weekDates[d];
                
                // スケジュールの期間と現在の日付が重なるかチェック
                if (currentDate >= startDate && currentDate <= endDate) {
                    if (schedule.all_day == 1) {
                        dailySchedules[currentDate].allDay.push(schedule);
                    } else {
                        // 開始日の場合は開始時間の時間帯に振り分け
                        if (currentDate === startDate) {
                            const startTime = moment(schedule.start_time).format('HH:mm');
                            const hourStr = startTime.substring(0, 2) + ':00';
                            
                            if (dailySchedules[currentDate].timeSlots[hourStr]) {
                                dailySchedules[currentDate].timeSlots[hourStr].push(schedule);
                            }
                        } else {
                            // 開始日以外の場合は0時の時間帯に振り分け
                            dailySchedules[currentDate].timeSlots['00:00'].push(schedule);
                        }
                    }
                }
            }
        });
        
        // HTML生成
        let html = '<div class="week-schedule">';
        
        // 曜日ヘッダー
        html += '<div class="week-header">';
        html += '<div class="week-time-column"></div>'; // 時間列のヘッダー
        
        weekDates.forEach(date => {
            const dayOfWeek = moment(date).format('ddd');
            const dayOfMonth = moment(date).format('D');
            const isToday = moment().format('YYYY-MM-DD') === date;
            const todayClass = isToday ? 'today' : '';
            
            html += `<div class="week-day ${todayClass}" data-date="${date}">
                        <div class="week-day-name">${dayOfWeek}</div>
                        <div class="week-day-number">${dayOfMonth}</div>
                    </div>`;
        });
        
        html += '</div>';
        
        // 終日スケジュール行
        html += '<div class="week-all-day-row">';
        html += '<div class="week-time-column">終日</div>';
        
        weekDates.forEach(date => {
            const allDayItems = dailySchedules[date].allDay;
            const isToday = moment().format('YYYY-MM-DD') === date;
            const todayClass = isToday ? 'today' : '';
            
            html += `<div class="week-day-content ${todayClass}" data-date="${date}">`;
            
            if (allDayItems.length > 0) {
                allDayItems.forEach(schedule => {
                    html += this.renderWeekScheduleItem(schedule, true);
                });
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        
        // 時間帯行
        for (let hour = 0; hour < 24; hour++) {
            const timeStr = hour.toString().padStart(2, '0') + ':00';
            
            html += '<div class="week-hour-row">';
            html += `<div class="week-time-column">${timeStr}</div>`;
            
            weekDates.forEach(date => {
                const hourItems = dailySchedules[date].timeSlots[timeStr];
                const isToday = moment().format('YYYY-MM-DD') === date;
                const todayClass = isToday ? 'today' : '';
                
                html += `<div class="week-day-content ${todayClass}" data-date="${date}" data-hour="${hour}">`;
                
                if (hourItems.length > 0) {
                    hourItems.forEach(schedule => {
                        html += this.renderWeekScheduleItem(schedule, false);
                    });
                }
                
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        html += '</div>';
        
        container.html(html);
        
        // セルのクリックイベント
        $('.week-day-content').on('click', function(e) {
            // スケジュールアイテムのクリックは除外
            if ($(e.target).closest('.schedule-item').length === 0) {
                const date = $(this).data('date');
                const hour = $(this).data('hour');
                
                if (date) {
                    if (hour !== undefined) {
                        const time = hour.toString().padStart(2, '0') + ':00';
                        window.location.href = '/schedule/create?date=' + date + '&time=' + time;
                    } else {
                        window.location.href = '/schedule/create?date=' + date + '&all_day=1';
                    }
                }
            }
        });
    },
    
    // 月単位スケジュールの読み込み
    loadMonthSchedules: function(year, month, userId) {
        App.apiGet('/schedule/month', { year: year, month: month, user_id: userId })
            .then(response => {
                if (response.success) {
                    const data = response.data;
                    this.renderMonthSchedules(data.schedules, year, month, data.days_in_month, data.first_day_of_week);
                } else {
                    App.showNotification('スケジュールの読み込みに失敗しました', 'error');
                }
            })
            .catch(error => {
                App.showNotification('スケジュールの読み込みに失敗しました', 'error');
                console.error(error);
            });
    },
    
    // 月単位スケジュールの表示
    renderMonthSchedules: function(schedules, year, month, daysInMonth, firstDayOfWeek) {
        const container = $('#month-schedule-container');
        
        // 日付ごとにスケジュールを整理
        const dailySchedules = {};
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = year + '-' + month.toString().padStart(2, '0') + '-' + day.toString().padStart(2, '0');
            dailySchedules[dateStr] = [];
        }
        
        // スケジュールを日付に振り分け
        schedules.forEach(schedule => {
            const startDate = moment(schedule.start_time).format('YYYY-MM-DD');
            const endDate = moment(schedule.end_time).format('YYYY-MM-DD');
            
            // スケジュールの期間内の各日に振り分け
            const start = moment(startDate);
            const end = moment(endDate);
            
            for (let m = moment(start); m.isSameOrBefore(end); m.add(1, 'days')) {
                const currentDate = m.format('YYYY-MM-DD');
                
                // 表示対象月のみ処理
                if (m.month() + 1 == month && m.year() == year) {
                    if (dailySchedules[currentDate]) {
                        dailySchedules[currentDate].push(schedule);
                    }
                }
            }
        });
        
        // カレンダーHTML生成
        let html = '<div class="month-calendar">';
        
        // 曜日ヘッダー
        html += '<div class="week-row header-row">';
        const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
        dayNames.forEach(day => {
            html += `<div class="day-cell day-name">${day}</div>`;
        });
        html += '</div>';
        
        // カレンダー本体
        let dayCount = 1;
        let cellCount = 0;
        
        // 最大6週間分のカレンダーを生成
        for (let week = 0; week < 6; week++) {
            html += '<div class="week-row">';
            
            // 1週間7日分のセルを生成
            for (let weekday = 0; weekday < 7; weekday++) {
                cellCount++;
                
                // 1日以前または月末以降の空白セル
                if ((week === 0 && weekday < firstDayOfWeek) || dayCount > daysInMonth) {
                    html += '<div class="day-cell empty-cell"></div>';
                } else {
                    const date = year + '-' + month.toString().padStart(2, '0') + '-' + dayCount.toString().padStart(2, '0');
                    const isToday = moment().format('YYYY-MM-DD') === date;
                    const todayClass = isToday ? 'today' : '';
                    const daySchedules = dailySchedules[date] || [];
                    const dayOfWeek = weekday; // 0(日)～6(土)
                    const weekendClass = (dayOfWeek === 0 || dayOfWeek === 6) ? 'weekend' : '';
                    
                    html += `<div class="day-cell calendar-day ${todayClass} ${weekendClass}" data-date="${date}">`;
                    html += `<div class="day-number">${dayCount}</div>`;
                    html += '<div class="day-content">';
                    
                    // 最大3件まで表示
                    const maxDisplay = 3;
                    const displaySchedules = daySchedules.slice(0, maxDisplay);
                    const remainingCount = Math.max(0, daySchedules.length - maxDisplay);
                    
                    displaySchedules.forEach(schedule => {
                        html += this.renderMonthScheduleItem(schedule);
                    });
                    
                    if (remainingCount > 0) {
                        html += `<div class="more-schedules">他 ${remainingCount} 件</div>`;
                    }
                    
                    html += '</div></div>';
                    
                    dayCount++;
                }
            }
            
            html += '</div>';
            
            // 月の最終日を表示したら終了
            if (dayCount > daysInMonth) {
                break;
            }
        }
        
        html += '</div>';
        
        container.html(html);
        
        // 日付セルのクリックイベント
        $('.calendar-day').on('click', function(e) {
            // スケジュールアイテムのクリックは除外
            if ($(e.target).closest('.schedule-item, .more-schedules').length === 0) {
                const date = $(this).data('date');
                window.location.href = '/schedule/day?date=' + date;
            }
        });
        
        // より多くのスケジュールをクリック
        $('.more-schedules').on('click', function() {
            const date = $(this).closest('.calendar-day').data('date');
            window.location.href = '/schedule/day?date=' + date;
        });
    },
    
    // スケジュールリストアイテムのレンダリング
    renderScheduleListItem: function(schedule) {
        const startTime = moment(schedule.start_time).format('HH:mm');
        const endTime = moment(schedule.end_time).format('HH:mm');
        const timeDisplay = schedule.all_day == 1 ? '終日' : startTime + ' - ' + endTime;
        const priorityClass = this.getPriorityClass(schedule.priority);
        
        return `
            <a href="/schedule/view/${schedule.id}" class="list-group-item list-group-item-action ${priorityClass}">
                <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">${schedule.title}</h5>
                    <small>${timeDisplay}</small>
                </div>
                <p class="mb-1">${schedule.description || ''}</p>
                <small>${schedule.creator_name}</small>
            </a>
        `;
    },
    
    // タイムライン用スケジュールアイテムのレンダリング
    renderScheduleTimelineItem: function(schedule) {
        const startTime = moment(schedule.start_time).format('HH:mm');
        const endTime = moment(schedule.end_time).format('HH:mm');
        const timeDisplay = startTime + ' - ' + endTime;
        const priorityClass = this.getPriorityClass(schedule.priority);
        
        return `
            <div class="schedule-item ${priorityClass}">
                <a href="/schedule/view/${schedule.id}">
                    <div class="schedule-time">${timeDisplay}</div>
                    <div class="schedule-title">${schedule.title}</div>
                </a>
            </div>
        `;
    },
    
    // 週表示用スケジュールアイテムのレンダリング
    renderWeekScheduleItem: function(schedule, isAllDay) {
        const startTime = moment(schedule.start_time).format('HH:mm');
        const endTime = moment(schedule.end_time).format('HH:mm');
        const timeDisplay = isAllDay ? '' : startTime + ' - ' + endTime;
        const priorityClass = this.getPriorityClass(schedule.priority);
        
        return `
            <div class="schedule-item ${priorityClass}">
                <a href="/schedule/view/${schedule.id}">
                    <div class="schedule-title">${schedule.title}</div>
                    ${timeDisplay ? '<div class="schedule-time">' + timeDisplay + '</div>' : ''}
                </a>
            </div>
        `;
    },
    
    // 月表示用スケジュールアイテムのレンダリング
    renderMonthScheduleItem: function(schedule) {
        const priorityClass = this.getPriorityClass(schedule.priority);
        const allDayClass = schedule.all_day == 1 ? 'all-day' : '';
        
        return `
            <div class="schedule-item ${priorityClass} ${allDayClass}">
                <a href="/schedule/view/${schedule.id}">
                    ${schedule.title}
                </a>
            </div>
        `;
    },
    
    // フォームのバリデーション
    validateForm: function() {
        let isValid = true;
        
        // 必須フィールドのチェック
        const requiredFields = ['title', 'start_time_date', 'end_time_date'];
        
        requiredFields.forEach(field => {
            const input = $('#' + field);
            
            if (!input.val().trim()) {
                input.addClass('is-invalid');
                input.next('.invalid-feedback').text('この項目は必須です');
                isValid = false;
            } else {
                input.removeClass('is-invalid');
            }
        });
        
        // 終日でない場合は時間も必須
        if (!$('#all_day').is(':checked')) {
            const timeFields = ['start_time_time', 'end_time_time'];
            
            timeFields.forEach(field => {
                const input = $('#' + field);
                
                if (!input.val().trim()) {
                    input.addClass('is-invalid');
                    input.next('.invalid-feedback').text('この項目は必須です');
                    isValid = false;
                } else {
                    input.removeClass('is-invalid');
                }
            });
        }
        
        // 開始日時と終了日時の整合性チェック
        const startDate = $('#start_time_date').val();
        const startTime = $('#start_time_time').val() || '00:00';
        const endDate = $('#end_time_date').val();
        const endTime = $('#end_time_time').val() || '23:59';
        
        const startDateTime = new Date(startDate + ' ' + startTime);
        const endDateTime = new Date(endDate + ' ' + endTime);
        
        if (startDateTime > endDateTime) {
            $('#end_time_date').addClass('is-invalid');
            $('#end_time_date').next('.invalid-feedback').text('終了日時は開始日時以降にしてください');
            isValid = false;
        }
        
        // 繰り返し設定の整合性チェック
        if ($('#repeat_type').val() !== 'none' && !$('#repeat_end_date').val()) {
            $('#repeat_end_date').addClass('is-invalid');
            $('#repeat_end_date').next('.invalid-feedback').text('繰り返し終了日を指定してください');
            isValid = false;
        }
        
        return isValid;
    },
    
    // 参加ステータスを更新
    updateParticipationStatus: function(scheduleId, status) {
        App.apiPost('/schedule/' + scheduleId + '/participation-status', { status: status })
            .then(response => {
                if (response.success) {
                    App.showNotification(response.message || '参加ステータスを更新しました', 'success');
                    
                    // ステータス表示を更新
                    this.updateParticipationStatusDisplay(status);
                } else {
                    App.showNotification(response.error || 'エラーが発生しました', 'error');
                }
            })
            .catch(error => {
                App.showNotification('エラーが発生しました', 'error');
                console.error(error);
            });
    },
    
    // 参加ステータス表示を更新
    updateParticipationStatusDisplay: function(status) {
        // ステータス表示テキスト
        const statusText = {
            'pending': '未回答',
            'accepted': '参加',
            'declined': '不参加',
            'tentative': '未定'
        };
        
        // ステータス表示クラス
        const statusClass = {
            'pending': 'bg-secondary',
            'accepted': 'bg-success',
            'declined': 'bg-danger',
            'tentative': 'bg-warning'
        };
        
        // 現在のステータス表示を更新
        $('#participation-status').text(statusText[status] || '未回答');
        $('#participation-status')
            .removeClass('bg-secondary bg-success bg-danger bg-warning')
            .addClass(statusClass[status] || 'bg-secondary');
        
        // ボタン状態を更新
        $('.btn-participation-status').removeClass('active');
        $(`.btn-participation-status[data-status="${status}"]`).addClass('active');
    },
    
    // 優先度に応じたカラークラスを取得
    getPriorityClass: function(priority) {
        switch (priority) {
            case 'high':
                return 'priority-high';
            case 'low':
                return 'priority-low';
            default:
                return 'priority-normal';
        }
    },
    
    // 優先度に応じたカラーコードを取得
    getPriorityColor: function(priority) {
        switch (priority) {
            case 'high':
                return '#d9534f'; // 赤（高）
            case 'low':
                return '#5bc0de'; // 青（低）
            default:
                return '#5cb85c'; // 緑（通常）
        }
    }
};
