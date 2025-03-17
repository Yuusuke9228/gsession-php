#!/bin/bash

# プロジェクトのルートディレクトリ
PROJECT_DIR="/var/www/html/gsession-php"
cd "$PROJECT_DIR"

# 絶対URLへのリダイレクトを修正する
echo "index.phpのリダイレクトURLを修正中..."
for pattern in \
    "header('Location: /schedule" \
    "header('Location: /login" \
    "header('Location: /logout"
do
    grep -l "$pattern" public/index.php | xargs sed -i "s|$pattern|header('Location: ' . BASE_PATH . '/schedule|g"
done

# フォームのアクション属性を修正
echo "views/auth/login.phpのフォームアクションを修正中..."
sed -i 's|<form action="/login|<form action="<?php echo BASE_PATH; ?>/login|g' views/auth/login.php

# 全てのPHPファイルでリンクを修正
echo "リンクのhref属性を修正中..."
for php_file in $(find . -name "*.php" -type f); do
    # リンクURL修正
    sed -i 's|href="/|href="<?php echo BASE_PATH; ?>/|g' "$php_file"
    
    # リソースURL修正
    sed -i 's|src="/|src="<?php echo BASE_PATH; ?>/|g' "$php_file"
done

# JavaScriptの相対パスを修正
echo "JavaScriptファイルのURLを修正中..."
for js_file in $(find ./public/js -name "*.js" -type f); do
    # APIエンドポイント修正
    sed -i 's|apiEndpoint: "/api"|apiEndpoint: BASE_PATH + "/api"|g' "$js_file"
    
    # window.location修正
    sed -i 's|window.location.href = "/|window.location.href = BASE_PATH + "/|g' "$js_file"
    
    # URLリクエスト修正
    sed -i 's|url: "/api/|url: BASE_PATH + "/api/|g' "$js_file"
    
    # BASE_PATH変数追加
    if ! grep -q "var BASE_PATH" "$js_file"; then
        # JavaScriptファイルの先頭にBASE_PATH変数定義を追加
        echo "// ベースパス設定
var BASE_PATH = '<?php echo BASE_PATH; ?>';" > "$js_file.tmp"
        cat "$js_file" >> "$js_file.tmp"
        mv "$js_file.tmp" "$js_file"
    fi
done

# Router.phpのリダイレクト修正
echo "Router.phpのリダイレクト修正中..."
sed -i "s|header('Location: /login|header('Location: ' . \$this->basePath . '/login|g" core/Router.php

echo "完了しました。重要なファイルを手動で確認してください。"
