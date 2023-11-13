<?php

namespace Kamakas\MakeEloquent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * モデル生成ツール
 */
class MakeModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:eloquent {tableName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Eloquent ModelとGetter/Setterを作成する';

    /**
     * Execute the console command.
     *
     * @return integer
     */
    public function handle()
    {
        $tableName = $this->argument('tableName');
        if (! Schema::hasTable($tableName)) {
            // 存在しないテーブルの場合エラー
            return 1;
        }
        //テンプレートを取得
        $modelTemplate = file_get_contents(__DIR__ . "/../../stubs/Model.stub");
        $eloquentTemplate = file_get_contents(__DIR__ . "/../../stubs/Eloquent.stub");
        $repositoryInterfaceTemplate = file_get_contents(__DIR__ . "/../../stubs/RepositoryInterface.stub");
        $eloquentRepositoryTemplate = file_get_contents(__DIR__ . "/../../stubs/Repository.stub");
        $factoryTemplate = file_get_contents(__DIR__ . "/../../stubs/Factory.stub");
        $seederTemplate = file_get_contents(__DIR__ . "/../../stubs/Seeder.stub");

        // カラム取得
        $columns = DB::select("show columns from {$tableName}");
        // プライマリキーを設定
        $primaryKey = '';
        $primaryKeyType = '';
        $primaryKeyExtra = '';
        foreach ($columns as $column) {
            if ($column->Key == "PRI") {
                $primaryKey = $column->Field;
                $primaryKeyType = $column->Type;
                $primaryKeyExtra = $column->Extra;
                break;
            }
        }
        // モデル名の取得
        $modelName = $this->getModelNameFromTableName($tableName);

        //生成するファイル名
        $modelPath = app_path("Domain/{$modelName}.php");
        $eloquentPath = app_path("Infrastructure/Eloquent/Eloquent{$modelName}.php");
        $repositoryInterfacePath = app_path("Domain/{$modelName}RepositoryInterface.php");
        $eloquentRepositoryPath = app_path("Infrastructure/Eloquent/Eloquent{$modelName}Repository.php");
        $factoryPath = database_path("factories/Infrastructure/Eloquent/Eloquent{$modelName}Factory.php");
        $seederPath = database_path("seeders/Tbl{$modelName}Seeder.php");

        //ファイルが存在すればスキップ
        $existModel = false;
        $existEloquent = false;
        $existRepositoryInterface = false;
        $existEloquentRepository = false;
        $existFactory = false;
        $existSeeder = false;
        if (file_exists($modelPath)) {
            $this->info("{$modelName}は既に存在しています。");
            $existModel = true;
        }
        if (file_exists($eloquentPath)) {
            $this->info("Eloquent{$modelName}は既に存在しています。");
            $existEloquent = true;
        }
        if (file_exists($repositoryInterfacePath)) {
            $this->info("{$modelName}RepositoryInterfaceは既に存在しています。");
            $existRepositoryInterface = true;
        }
        if (file_exists($eloquentRepositoryPath)) {
            $this->info("Eloquent{$modelName}Repositoryは既に存在しています。");
            $existEloquentRepository = true;
        }
        if (file_exists($factoryPath)) {
            $this->info("Eloquent{$modelName}Factoryは既に存在しています。");
            $existFactory = true;
        }
        if (file_exists($seederPath)) {
            $this->info("Tbl{$modelName}Seederは既に存在しています。");
            $existSeeder = true;
        }

        //コメント取得
        $status = DB::select("show table status like '{$tableName}'");
        $tableComment = $status[0]->Comment;

        // Getter/Setterモデルの生成
        if (false === $existModel) {
            $body = $modelTemplate;
            $body = str_replace("{TableComment}", $tableComment, $body);
            $body = str_replace("{ModelName}", $modelName, $body);
            $body = str_replace("{Properties}", $this->makePropertiesString($columns), $body);
            $body = str_replace("{Arguments}", $this->makeArgumentsString($columns), $body);

            //ファイル出力
            file_put_contents($modelPath, $body);

            $this->info("{$modelName}を作成しました。");
        }

        // Eloquentモデルの生成
        if (false === $existEloquent) {
            $body = $eloquentTemplate;
            $body = str_replace("{ModelName}", $modelName, $body);
            $body = str_replace("{TableComment}", $tableComment, $body);
            $body = str_replace("{TableName}", $tableName, $body);
            $body = str_replace("{PrimaryKey}", $primaryKey, $body);
            // 主キーがオートインクリメントじゃない場合はincrementingをfalseにする
            if ($primaryKeyExtra === '') {
                $body = str_replace(
                    "{Incrementing}",
                    "\n    /**\n     * @var boolean\n     */\n    public \$incrementing = false;\n",
                    $body
                );
            } else {
                $body = str_replace("{Incrementing}", '', $body);
            }
            // 主キーが文字列型の場合はkeyTypeをstringにする
            if (
                strpos($primaryKeyType, "varchar") === 0
                || strpos($primaryKeyType, "char") === 0
                || strpos($primaryKeyType, "text") === 0
            ) {
                $body = str_replace(
                    "{KeyType}",
                    "\n    /**\n     * @var string\n     */\n    protected \$keyType = 'string';\n",
                    $body
                );
            } else {
                $body = str_replace("{KeyType}", '', $body);
            }
            $body = str_replace("{Casts}", $this->makeCastsString($columns), $body);
            $body = str_replace("{Fillable}", $this->makeFillableString($columns), $body);
            $body = str_replace("{Columns}", $this->makeColumnsString($columns), $body);
            $isSoftDelets = false;
            foreach ($columns as $column) {
                if ($column->Field == "deleted_at") {
                    $isSoftDelets = true;
                    break;
                }
            }
            if ($isSoftDelets) {
                $body = str_replace("{importSoftDeletes}", "\nuse Illuminate\Database\Eloquent\SoftDeletes;", $body);
                $body = str_replace("{useSoftDeletes}", "\n    use SoftDeletes;", $body);
            } else {
                $body = str_replace("{importSoftDeletes}", '', $body);
                $body = str_replace("{useSoftDeletes}", '', $body);
            }

            //ファイル出力
            file_put_contents($eloquentPath, $body);

            $this->info("Eloquent{$modelName}を作成しました。");
        }

        // リポジトリインターフェースの生成
        if (false === $existRepositoryInterface) {
            $body = $repositoryInterfaceTemplate;
            $body = str_replace("{ModelName}", $modelName, $body);
            $body = str_replace("{lowerModelName}", lcfirst($modelName), $body);
            $body = str_replace("{TableComment}", $tableComment, $body);

            //ファイル出力
            file_put_contents($repositoryInterfacePath, $body);

            $this->info("{$modelName}RepositoryInterfaceを作成しました。");
        }

        // Eloqunetリポジトリの生成
        if (false === $existEloquentRepository) {
            $body = $eloquentRepositoryTemplate;
            $body = str_replace("{ModelName}", $modelName, $body);
            $body = str_replace("{lowerModelName}", lcfirst($modelName), $body);
            $body = str_replace("{TableComment}", $tableComment, $body);
            $body = str_replace("{PrimaryKey}", $primaryKey, $body);

            //ファイル出力
            file_put_contents($eloquentRepositoryPath, $body);

            $this->info("Eloquent{$modelName}Repositoryを作成しました。");
        }

        // ファクトリの生成
        if (false === $existFactory) {
            $body = $factoryTemplate;
            $body = str_replace("{ModelName}", $modelName, $body);
            $body = str_replace("{Definition}", $this->makeFactoryDefinition($columns), $body);

            //ファイル出力
            file_put_contents($factoryPath, $body);

            $this->info("Eloquent{$modelName}Factoryを作成しました。");
        }

        // シーダーの生成
        if (false === $existSeeder) {
            $body = $seederTemplate;
            $body = str_replace("{ModelName}", $modelName, $body);
            $body = str_replace("{Data}", $this->makeSeederData($columns), $body);

            //ファイル出力
            file_put_contents($seederPath, $body);

            $this->info("Tbl{$modelName}Seederを作成しました。");
        }

        if (false === $existRepositoryInterface || false === $existEloquentRepository) {
            $this->info("bootstrap/app.phpへの定義追加をお忘れなく！");
        }

        return 0;
    }

    /**
     * テーブル名からモデル名を取得する
     *
     * @param string $tableName
     * @return string
     */
    private function getModelNameFromTableName(string $tableName)
    {
        // 先頭のtbl_またはmst_を無視
        $snake = substr($tableName, 4);
        $words = explode('_', $snake);
        $camel = join('', array_map(function ($word) {
            return ucfirst($word);
        }, $words));
        return $camel;
    }

    /**
     * @param string $word
     * @return string
     */
    private function changeSnakeToCamel(string $word)
    {
        return lcfirst(strtr(ucwords(strtr($word, ['_' => ' '])), [' ' => '']));
        ;
    }

    /**
     * プロパティ文字列を生成する
     *
     * @param array<mixed> $columns
     * @return string
     */
    private function makePropertiesString(array $columns)
    {
        $properties = [];

        foreach ($columns as $column) {
            if ($column->Field == "created_at" || $column->Field == "updated_at" || $column->Field == "deleted_at") {
                continue;
            }

            if (strpos($column->Type, "int") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'integer';
            } elseif (strpos($column->Type, "tinyint") === 0 && strpos($column->Field, "flg")) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'boolean';
            } elseif (strpos($column->Type, "tinyint") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'integer';
            } elseif (strpos($column->Type, "bigint") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'integer';
            } elseif (strpos($column->Type, "decimal") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'float';
            } elseif (strpos($column->Type, "varchar") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'string';
            } elseif (strpos($column->Type, "char") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'string';
            } elseif (strpos($column->Type, "text") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'string';
            } elseif (strpos($column->Type, "datetime") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'Carbon';
            } elseif (strpos($column->Type, "timestamp") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'Carbon';
            } elseif (strpos($column->Type, "date") === 0) {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'Carbon';
            } else {
                $properties[$this->changeSnakeToCamel($column->Field)] = 'string';
            }

            if ($column->Null === "YES") {
                $properties[$this->changeSnakeToCamel($column->Field)] .= '|null';
            }
        }

        return join("\n     * ", array_map(function ($key, $value) {
            return "@param {$value} \${$key}";
        }, array_keys($properties), array_values($properties)));
    }

    /**
     * 引数文字列を生成する
     *
     * @param array<mixed> $columns
     * @return string
     */
    private function makeArgumentsString(array $columns)
    {
        $arguments = [];

        foreach ($columns as $column) {
            if ($column->Field == "created_at" || $column->Field == "updated_at" || $column->Field == "deleted_at") {
                continue;
            }

            if (strpos($column->Type, "int") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'int';
            } elseif (strpos($column->Type, "tinyint") === 0 && strpos($column->Field, "flg")) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'bool';
            } elseif (strpos($column->Type, "tinyint") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'int';
            } elseif (strpos($column->Type, "bigint") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'int';
            } elseif (strpos($column->Type, "decimal") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'float';
            } elseif (strpos($column->Type, "varchar") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'string';
            } elseif (strpos($column->Type, "char") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'string';
            } elseif (strpos($column->Type, "text") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'string';
            } elseif (strpos($column->Type, "datetime") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'Carbon';
            } elseif (strpos($column->Type, "timestamp") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'Carbon';
            } elseif (strpos($column->Type, "date") === 0) {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'Carbon';
            } else {
                $arguments[$this->changeSnakeToCamel($column->Field)] = 'string';
            }

            if ($column->Null === "YES") {
                $arguments[$this->changeSnakeToCamel($column->Field)] = '?'
                    . $arguments[$this->changeSnakeToCamel($column->Field)];
            }
        }

        return rtrim(join("\n        ", array_map(function ($key, $value) {
            return "public readonly {$value} \${$key},";
        }, array_keys($arguments), array_values($arguments))), ',');
    }

    /**
     * Casts文字列を生成する
     *
     * @param array<mixed> $columns
     * @return string
     */
    private function makeCastsString(array $columns)
    {
        $casts = [];

        foreach ($columns as $column) {
            if ($column->Field == "created_at" || $column->Field == "updated_at") {
                continue;
            }
            if (strpos($column->Type, "datetime") === 0) {
                $casts[$column->Field] = 'datetime';
            } elseif (strpos($column->Type, "timestamp") === 0) {
                $casts[$column->Field] = 'datetime';
            } elseif (strpos($column->Type, "date") === 0) {
                $casts[$column->Field] = 'date';
            } elseif (strpos($column->Type, "tinyint") === 0 && strpos($column->Field, "flg")) {
                $casts[$column->Field] = 'boolean';
            }
        }

        return join("\n        ", array_map(function ($key, $value) {
            return "'{$key}' => '{$value}',";
        }, array_keys($casts), array_values($casts)));
    }

    /**
     * Fillable文字列を生成する
     *
     * @param array<mixed> $columns
     * @return string
     */
    private function makeFillableString(array $columns)
    {
        $fillable = [];

        foreach ($columns as $column) {
            if ($column->Key == "PRI") {
                continue;
            }
            if ($column->Field == "created_at" || $column->Field == "updated_at" || $column->Field == "deleted_at") {
                continue;
            }
            $fillable[] = $column->Field;
        }

        return join("\n        ", array_map(function ($value) {
            return "'{$value}',";
        }, array_values($fillable)));
    }

    /**
     * カラム文字列を生成する
     *
     * @param array<mixed> $columns
     * @return string
     */
    private function makeColumnsString(array $columns)
    {
        $modelColumns = [];

        foreach ($columns as $column) {
            if ($column->Field == "created_at" || $column->Field == "updated_at" || $column->Field == "deleted_at") {
                continue;
            }
            $modelColumns[] = $column->Field;
        }

        return rtrim(join("\n            ", array_map(function ($value) {
            return "\$this->{$value},";
        }, array_values($modelColumns))), ',');
    }

    /**
     * Factory定義を生成する
     *
     * @param array<mixed> $columns
     * @return string
     */
    private function makeFactoryDefinition(array $columns)
    {
        $definition = [];

        foreach ($columns as $column) {
            if ($column->Key == "PRI") {
                continue;
            }
            if ($column->Field == "created_at" || $column->Field == "updated_at" || $column->Field == "deleted_at") {
                continue;
            }
            if (strpos($column->Type, "int") === 0) {
                $definition[$column->Field] = '$this->faker->randomNumber()';
            } elseif (strpos($column->Type, "tinyint") === 0 && strpos($column->Field, "flg")) {
                $definition[$column->Field] = '$this->faker->boolean()';
            } elseif (strpos($column->Type, "tinyint") === 0) {
                $definition[$column->Field] = '$this->faker->randomNumber()';
            } elseif (strpos($column->Type, "bigint") === 0) {
                $definition[$column->Field] = '$this->faker->randomNumber()';
            } elseif (strpos($column->Type, "decimal") === 0) {
                $definition[$column->Field] = '$this->faker->randomFloat()';
            } elseif (strpos($column->Type, "varchar") === 0) {
                $definition[$column->Field] = '$this->faker->name()';
            } elseif (strpos($column->Type, "char") === 0) {
                $definition[$column->Field] = '$this->faker->word()';
            } elseif (strpos($column->Type, "text") === 0) {
                $definition[$column->Field] = '$this->faker->text()';
            } elseif (strpos($column->Type, "datetime") === 0) {
                $definition[$column->Field] = '$this->faker->dateTime()';
            } elseif (strpos($column->Type, "timestamp") === 0) {
                $definition[$column->Field] = '$this->faker->dateTime()';
            } elseif (strpos($column->Type, "date") === 0) {
                $definition[$column->Field] = '$this->faker->dateTime()';
            } else {
                $definition[$column->Field] = '$this->faker->text()';
            }
        }

        return join("\n            ", array_map(function ($key, $value) {
            return "'{$key}' => {$value},";
        }, array_keys($definition), array_values($definition)));
    }

    /**
     * Seederデータを生成する
     * @param array<mixed> $columns
     * @return string
     */
    private function makeSeederData(array $columns)
    {
        $data = '[';
        foreach ($columns as $column) {
            if ($column->Key == "PRI") {
                continue;
            }
            if ($column->Field == "created_at" || $column->Field == "updated_at" || $column->Field == "deleted_at") {
                continue;
            }
            $data .= "\n                '{$column->Field}' => '',";
        }
        return $data .= "\n            ],";
    }
}
