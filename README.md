# インストール
プライベートリポジトリのため、まずはアクセストークンの設定を行います。
```
composer config repositories.kamakas/make-eloquent-model vcs https://github.com/kamakas/make-eloquent-model
composer config github-oauth.github.com アクセストークン
```
それが終わったら実際にインストールをします。
```
composer require --dev kamakas/make-eloquent-model
```
※本番環境で使用することはないため必ずdevオプションをつけてください。

# 使い方
テーブル作成後、Domainモデル・Eloquentモデル・Domainリポジトリ・Eloquentリポジトリ・Factory・Seederを自動で生成することができます。  
すでに同名のphpファイルが存在する場合は生成しません。
```
php artisan make:eloquent {table_name}
```
