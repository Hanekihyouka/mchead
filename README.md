# mc-skin-render

用 PHP 实现的 Minecraft 玩家头像 / 全身 3D 渲染 API。它是 [McHead](https://github.com/mchorse/mchead) 的服务端重构版：McHead 在浏览器里用 ThreeJS 渲染，这里改为通过 HTTP 直接返回 PNG 图片。

## 接口

| 接口 | 说明 |
| --- | --- |
| `head.php` | 3D 头像（含帽子层），512×512 PNG |
| `body.php` | 3D 全身（头、身体、四肢及外层），512×768 PNG |

玩家通过 UUID 指定，两种写法都可以，UUID 支持带或不带横线、大小写不敏感：

```
/head.php?uuid=069a79f444e94726a5befca90e38aaf5
/head.php/069a79f444e94726a5befca90e38aaf5.png
/body.php?uuid=069a79f444e94726a5befca90e38aaf5
/body/069a79f444e94726a5befca90e38aaf5.png        (需要 Apache + .htaccess)
```

错误时返回 JSON `{"error": "..."}`：

| 状态码 | 原因 |
| --- | --- |
| 400 | UUID 缺失或格式不正确 |
| 404 | 玩家不存在，或玩家没有皮肤 |
| 422 | 皮肤尺寸不是 64×64 或 64×32 |
| 502 | 上游（Mojang）请求失败或返回异常内容 |
| 500 | 其他内部错误 |

## 缓存

缓存目录（都在项目根目录下，文件名为 `<uuid>.png`，uuid 统一为 32 位小写、无横线）：

- `skin/`：Mojang 下载的原始皮肤文件
- `head/`：头像渲染结果
- `body/`：全身渲染结果

请求流程：先检查 `head/` 或 `body/` 中是否已有文件，有则直接返回；没有则依次取 `skin/` 中的皮肤（没有则从 Mojang 下载并保存），渲染后写入缓存并返回。

缓存**永不更新**：文件一旦生成就不再覆盖。玩家改了皮肤，需要手动删除对应的缓存文件才会重新生成。写入先写临时文件再 `rename`，并发请求不会读到半截的 PNG。响应头带 `Cache-Control: public, max-age=86400`。

## 环境要求

- PHP 8.3
- `ext-gd`（PNG 读写与绘制）
- 服务器需要允许 `allow_url_fopen`（用于请求 Mojang 的 HTTP 接口）
- 不依赖 ImageMagick

## 渲染说明

- 使用纯 PHP 的软件光栅化：正交投影、z-buffer、最近邻采样，不做光照，颜色与 McHead 的 `MeshBasicMaterial` 一致。
- 视角与 McHead 默认视角相同（yaw = pitch = π/4）。
- 模型几何与 UV 布局采用原版 Minecraft 1.8+ 玩家模型。Slim（Alex，3 像素宽手臂）根据 Mojang 资料中的 `metadata.model` 判断。生成全身图时每次都要查询一次资料（即使皮肤已缓存），头像则不需要。
- 64×32 的旧版皮肤会先转换为 64×64 布局：右侧肢体镜像到左侧，并清除不存在的帽子层。
- 渲染参数（视角、尺寸）是固定的，不支持通过 URL 修改。缓存只按 UUID 命名，如果允许自定义参数，同一个 UUID 的结果就会不一致。

## 目录结构

```
bootstrap.php          自动加载与环境检查
head.php / body.php    API 入口
.htaccess              Apache 路径重写
src/
  Api.php              请求解析、响应输出
  Service.php          缓存优先的业务流程
  MojangClient.php     Mojang 资料与皮肤下载（只允许 textures.minecraft.net）
  Storage.php          缓存文件读写（原子写入、不覆盖）
  Skin.php / Image.php 皮肤校验、旧版皮肤转换、PNG 编解码
  Uuid.php             UUID 规范化
  Render/              模型几何（Model、Box）与光栅化（Rasterizer）
tests/render_check.php 朝向自检（不依赖网络）
skin/ head/ body/      缓存目录（生成的 PNG 不纳入 git）
```

## 测试

```sh
php tests/render_check.php
```

该脚本使用一张渐变测试皮肤，检查正面朝向没有镜像或上下翻转。

本地调试可以用内置服务器（路径形式的请求即可，无需 `.htaccess`）：

```sh
php -S 127.0.0.1:8080
curl -o head.png http://127.0.0.1:8080/head.php/069a79f444e94726a5befca90e38aaf5
```

## 许可

渲染逻辑参考了 McHead（MIT License，Copyright (c) 2016 McHorse）的模型定义与默认视角。
