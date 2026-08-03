# plato-api-contract

[English](README.md) | [简体中文](README.zh-CN.md)

面向 PHP 框架的契约优先控制器与 OpenAPI 3.1 生成工具，内置平台为 PlatoPHP，其他框架可以通过可信的
PHP Template Pack 接入。契约既可以用类似 Laravel FormRequest 的 `rules()` 描述，也可以复用应用自己的
readonly DTO。生成器每次更新控制器，只在 Logic 不存在时创建骨架，并用 manifest 记录生成物所有权。

## 安装与要求

- PHP 8.2+
- 使用内置 `plato` 平台时需要 PlatoPHP
- Node.js 仅用于本仓库开发阶段的 OpenAPI lint

宿主应用必须把 `lumnd/plato-api-contract` 安装为生产依赖。生成的 Plato Controller 和 Logic 签名会
使用本包的 `Runtime\ApiContext`、`Runtime\Input` 与 `Runtime\Dto`；如果只放在 `require-dev`，执行
`composer install --no-dev` 后生成代码将无法运行。

本仓库开发时通过相邻的 `../platophp` path repository 加载 PlatoPHP。安装并验证本仓库：

```bash
composer install
npm install
composer verify
```

## 契约

```php
use function Lumnd\PlatoApiContract\Dsl\post;
use function Lumnd\PlatoApiContract\Dsl\rules;

return [
    'syntax' => 'v1',
    'services' => [
        'auth' => post(
            '/auth/login',
            rules([
                'email'    => ['required', 'string', 'email'],
                'code'     => ['required', 'string', 'size:6'],
                'remember' => ['boolean', 'default:false'],
            ]),
            rules([
                'token'      => ['string'],
                'expires_at' => ['string', 'nullable'],
            ]),
            auth: 'none',
        ),
    ],
];
```

请求和响应都用同一套 rules 写法。每个请求字段必须表态能否缺省——`required`、`nullable` 或
`default:`——生成的控制器会把每个字段按声明的类型投影出来，因此 Logic 一定拿得到声明过的 key。
Logic 收发普通数组，骨架上会带对应的 PHPStan array shape。

需要强类型边界时，把请求或响应换成 readonly DTO class 即可，两侧可以各自选择；两种形式编译到同一
套 IR 和 OpenAPI。

`auth` 取 `required`（默认）、`optional` 或 `none`，直接生成进控制器 `$actions` 交给 PlatoPHP 路由。
不再有 permission DSL、`api_access.php` 或控制器重复 guard。

## 命令与目录

```bash
vendor/bin/plato api:lint
vendor/bin/plato api:generate
vendor/bin/plato api:check
```

三个 PlatoPHP 命令统一读取根 `plato.config.php` 的 `api_contract`。独立的 `vendor/bin/plato-api`
默认读取 `api-contract.php`，并支持 `--config=/任意位置/options.php`。通过
`--platform=/路径/platform.php` 可以加载框架模板包，不需要另外实现 Adapter 类。

工具专用目录只有 `contracts/` 与可选的 `templates/`。运行时代码继续放项目已有的 control / Logic
目录，不要求 `dto`、`contract` 或 `generated` 目录。

Controller 与 OpenAPI 属于生成器，manifest 会记录其 hash；遇到无法解释的手工修改时生成会中止。
修改应移入 Logic 或模板，只有确定要丢弃修改时才使用 `--force`。Logic 属于应用，只在不存在时创建，
之后不会被读取、覆盖或删除。`api:check` 执行同样的比对但不写文件；发现缺失、过期、被修改或已废弃
的生成物时退出码为 `4`。

详见 [DSL](docs/dsl.md)、[配置](docs/configuration.md) 与 [模板](docs/templates.md)。
