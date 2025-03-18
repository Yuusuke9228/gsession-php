/**
 * GroupWare - メインアプリケーションJS
 */

// アプリケーション名前空間
const App = {
    // 設定
    config: {
        apiEndpoint: BASE_PATH + '/api',
        dateFormat: 'YYYY-MM-DD',
        timeFormat: 'HH:mm',
        datetimeFormat: 'YYYY-MM-DD HH:mm'
    },

    // 現在のページ
    currentPage: null,

    // 初期化
    init: function () {
        // イベントリスナーを設定
        this.setupEventListeners();

        // 現在のページを判断して初期化
        this.initCurrentPage();

        // モーダルの初期化
        this.initModals();

        // 通知系の初期化
        this.initNotifications();
    },

    // イベントリスナーを設定
    setupEventListeners: function () {
        // ナビゲーションリンクのクリックイベント
        $(document).on('click', 'a[data-page]', function (e) {
            e.preventDefault();
            const page = $(this).data('page');
            const url = $(this).attr('href');

            // ページ遷移
            App.navigateTo(url, page);
        });

        // モーダル内のフォーム送信
        $(document).on('submit', '.modal-form', function (e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action');
            const method = form.attr('method') || 'POST';
            const data = form.serialize();

            App.submitForm(url, method, data, form);
        });

        // 通常のフォーム送信
        $(document).on('submit', 'form:not(.modal-form)', function (e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action');
            const method = form.attr('method') || 'POST';
            const data = form.serialize();

            App.submitForm(url, method, data, form);
        });

        // 削除ボタンのクリック
        $(document).on('click', '.btn-delete', function (e) {
            e.preventDefault();
            const url = $(this).data('url');
            const message = $(this).data('confirm') || '本当に削除しますか？';

            if (confirm(message)) {
                App.apiDelete(url)
                    .then(response => {
                        if (response.success) {
                            App.showNotification(response.message || '削除しました', 'success');

                            // データテーブルがある場合は再読み込み
                            if ($.fn.DataTable.isDataTable('.datatable')) {
                                $('.datatable').DataTable().ajax.reload();
                            } else {
                                // 現在のページをリロード
                                App.reloadCurrentPage();
                            }
                        } else {
                            App.showNotification(response.error || 'エラーが発生しました', 'error');
                        }
                    })
                    .catch(error => {
                        App.showNotification('エラーが発生しました', 'error');
                        console.error(error);
                    });
            }
        });
    },

    // 現在のページを判断して初期化
    initCurrentPage: function () {
        // URLから現在のページを判断
        const path = window.location.pathname;

        if (path.includes('/organizations')) {
            this.currentPage = 'organizations';
            // 組織管理ページの初期化
            if (typeof Organization !== 'undefined') {
                Organization.init();
            }
        } else if (path.includes('/users')) {
            this.currentPage = 'users';
            // ユーザー管理ページの初期化
            if (typeof User !== 'undefined') {
                User.init();
            }
        } else if (path.includes('/schedule')) {
            this.currentPage = 'schedule';
            // スケジュール管理ページの初期化
            if (typeof Schedule !== 'undefined') {
                Schedule.init();
            }
        }
    },

    // モーダルの初期化
    initModals: function () {
        // Bootstrap モーダルのイベント設定
        $('.modal').on('shown.bs.modal', function () {
            $(this).find('[autofocus]').focus();
        });

        // モーダルが閉じられたときにフォームをリセット
        $('.modal').on('hidden.bs.modal', function () {
            $(this).find('form').get(0)?.reset();
            $(this).find('.is-invalid').removeClass('is-invalid');
            $(this).find('.invalid-feedback').text('');
        });
    },

    // 通知系の初期化
    initNotifications: function () {
        // トースト通知の設定
        toastr.options = {
            closeButton: true,
            debug: false,
            newestOnTop: true,
            progressBar: true,
            positionClass: "toast-top-right",
            preventDuplicates: false,
            onclick: null,
            showDuration: "300",
            hideDuration: "1000",
            timeOut: "5000",
            extendedTimeOut: "1000",
            showEasing: "swing",
            hideEasing: "linear",
            showMethod: "fadeIn",
            hideMethod: "fadeOut"
        };
    },

    // 通知を表示
    showNotification: function (message, type = 'info') {
        switch (type) {
            case 'success':
                toastr.success(message);
                break;
            case 'error':
                toastr.error(message);
                break;
            case 'warning':
                toastr.warning(message);
                break;
            default:
                toastr.info(message);
        }
    },

    // ページ遷移
    navigateTo: function (url, page) {
        // 履歴に追加
        history.pushState({ page: page }, null, url);

        // コンテンツを読み込む
        this.loadPage(url);
    },

    // ページをロード
    loadPage: function (url) {
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'html',
            beforeSend: function () {
                // ローディングインジケータを表示
                $('#page-loader').show();
            },
            success: function (html) {
                // メインコンテンツを更新
                const mainContent = $(html).find('#main-content').html();
                $('#main-content').html(mainContent);

                // ナビゲーションメニューのアクティブ状態を更新
                const activePage = $(html).find('nav .active').data('page');
                $('nav .nav-link').removeClass('active');
                $('nav [data-page="' + activePage + '"]').addClass('active');

                // ページタイトルを更新
                document.title = $(html).filter('title').text();

                // 現在のページを更新
                App.currentPage = activePage;

                // ページ固有の初期化
                App.initCurrentPage();
            },
            error: function (xhr, status, error) {
                App.showNotification('ページの読み込みに失敗しました', 'error');
                console.error(error);
            },
            complete: function () {
                // ローディングインジケータを非表示
                $('#page-loader').hide();
            }
        });
    },

    // 現在のページをリロード
    reloadCurrentPage: function () {
        this.loadPage(window.location.href);
    },

    // フォーム送信
    submitForm: function (url, method, data, form) {
        $.ajax({
            url: url,
            type: method,
            data: data,
            beforeSend: function () {
                // 送信ボタンを無効化
                form.find('[type="submit"]').prop('disabled', true);

                // バリデーションエラー表示をクリア
                form.find('.is-invalid').removeClass('is-invalid');
                form.find('.invalid-feedback').text('');
            },
            success: function (response) {
                if (response.success) {
                    // 成功の場合
                    App.showNotification(response.message || '保存しました', 'success');

                    // モーダルがある場合は閉じる
                    const modal = form.closest('.modal');
                    if (modal.length) {
                        modal.modal('hide');
                    }

                    // リダイレクト指定があればリダイレクト
                    if (response.redirect) {
                        window.location.href = response.redirect;
                        return;
                    }

                    // データテーブルがある場合は再読み込み
                    if ($.fn.DataTable.isDataTable('.datatable')) {
                        $('.datatable').DataTable().ajax.reload();
                    } else {
                        // 現在のページをリロード
                        App.reloadCurrentPage();
                    }
                } else {
                    // エラーの場合
                    App.showNotification(response.error || 'エラーが発生しました', 'error');

                    // バリデーションエラーがある場合は表示
                    if (response.validation) {
                        for (const field in response.validation) {
                            const errorMsg = response.validation[field];
                            const input = form.find('[name="' + field + '"]');
                            input.addClass('is-invalid');
                            input.next('.invalid-feedback').text(errorMsg);
                        }
                    }
                }
            },
            error: function (xhr, status, error) {
                App.showNotification('エラーが発生しました', 'error');
                console.error(error);
            },
            complete: function () {
                // 送信ボタンを有効化
                form.find('[type="submit"]').prop('disabled', false);
            }
        });
    },

    // API GET リクエスト
    apiGet: function (endpoint, params = {}) {
        const url = this.buildApiUrl(endpoint, params);

        return fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            });
    },

    // API POST リクエスト
    apiPost: function (endpoint, data = {}) {
        const url = this.buildApiUrl(endpoint);

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            });
    },

    // API PUT リクエスト
    apiPut: function (endpoint, data = {}) {
        const url = this.buildApiUrl(endpoint);

        return fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            });
    },

    // API DELETE リクエスト
    apiDelete: function (endpoint) {
        const url = this.buildApiUrl(endpoint);

        return fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            });
    },

    // API URLを構築
    buildApiUrl: function (endpoint, params = {}) {
        // エンドポイントが既にBASE_PATHで始まっている場合は追加しない
        let url = endpoint;
        if (!endpoint.startsWith(BASE_PATH) && !endpoint.startsWith('/api')) {
            url = this.config.apiEndpoint + endpoint;
        } else if (endpoint.startsWith('/api')) {
            url = BASE_PATH + endpoint;
        }

        // GETパラメータを追加
        if (Object.keys(params).length > 0) {
            const queryString = new URLSearchParams(params).toString();
            url += (url.includes('?') ? '&' : '?') + queryString;
        }

        return url;
    },

    // 日付フォーマット（YYYY-MM-DD）
    formatDate: function (date) {
        if (!date) return '';

        if (typeof date === 'string') {
            date = new Date(date);
        }

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    },

    // 時間フォーマット（HH:MM）
    formatTime: function (date) {
        if (!date) return '';

        if (typeof date === 'string') {
            date = new Date(date);
        }

        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${hours}:${minutes}`;
    },

    // 日時フォーマット（YYYY-MM-DD HH:MM）
    formatDateTime: function (date) {
        if (!date) return '';

        return this.formatDate(date) + ' ' + this.formatTime(date);
    },

    // 日本語形式の日付フォーマット（YYYY年MM月DD日）
    formatDateJP: function (date) {
        if (!date) return '';

        if (typeof date === 'string') {
            date = new Date(date);
        }

        const year = date.getFullYear();
        const month = date.getMonth() + 1;
        const day = date.getDate();

        return `${year}年${month}月${day}日`;
    },

    // 曜日を取得（日本語）
    getDayOfWeekJP: function (date) {
        if (!date) return '';

        if (typeof date === 'string') {
            date = new Date(date);
        }

        const dayOfWeek = date.getDay();
        const dayNames = ['日', '月', '火', '水', '木', '金', '土'];

        return dayNames[dayOfWeek];
    },

    // 文字列を日付オブジェクトに変換
    parseDate: function (dateStr) {
        if (!dateStr) return null;

        // YYYY-MM-DD または YYYY/MM/DD 形式を想定
        const parts = dateStr.split(/[-\/]/);
        if (parts.length !== 3) return null;

        return new Date(parts[0], parts[1] - 1, parts[2]);
    },

    // 文字列を日時オブジェクトに変換
    parseDateTime: function (dateTimeStr) {
        if (!dateTimeStr) return null;

        // YYYY-MM-DD HH:MM:SS または YYYY/MM/DD HH:MM:SS 形式を想定
        const [dateStr, timeStr] = dateTimeStr.split(' ');
        if (!dateStr || !timeStr) return null;

        const dateParts = dateStr.split(/[-\/]/);
        if (dateParts.length !== 3) return null;

        const timeParts = timeStr.split(':');
        if (timeParts.length < 2) return null;

        return new Date(
            dateParts[0],
            dateParts[1] - 1,
            dateParts[2],
            timeParts[0],
            timeParts[1],
            timeParts[2] || 0
        );
    },

    // HTML特殊文字をエスケープ
    escapeHtml: function (text) {
        if (!text) return '';

        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    // 文字列を切り詰めて省略記号を追加
    truncateText: function (text, length = 50) {
        if (!text) return '';

        if (text.length <= length) return text;

        return text.substring(0, length) + '...';
    }
};

// DOMが読み込まれたら初期化
$(document).ready(function () {
    App.init();

    // ブラウザのバック/フォワードボタンでの履歴操作
    $(window).on('popstate', function (event) {
        if (event.originalEvent.state) {
            const url = location.href;
            App.loadPage(url);
        }
    });
});