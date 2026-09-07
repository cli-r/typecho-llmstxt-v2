# Typecho LlmstxtV2 Plugin

[中文说明](#中文说明) | [English Documentation](#english-documentation)

---

## 中文说明

本插件基于最新的 [llms.txt v2 规范](https://llmstxt.org/) 开发，旨在为 Typecho 博客生成对大语言模型（如 ChatGPT、Claude、Kimi 等）高度友好的站点结构。它不仅生成全局索引，还为每篇文章提供纯净的 Markdown 版本。

### 核心特性

* **完整兼容 v2 规范**：自动生成 `/llms.txt` 主索引，并为文章生成独立的纯净 `.md` 文件。
* **前端标识注入**：自动在网页头部注入 `rel="alternate"` 和 `rel="describedby"` 标签，引导 AI 爬虫抓取。
* **伪静态隔离**：静态物理文件受服务器内部规则保护，防范越权扫描。

### 安装与配置

1. 将插件文件夹重命名为 `LlmstxtV2`，并上传至 `usr/plugins/` 目录。
2. 登录 Typecho 后台，在“控制台 - 插件”中启用该插件。
3. **关键步骤**：请务必根据您的 Web 服务器环境，配置以下伪静态重写规则，以保护物理路径并接管 `.md` 请求：

**Nginx 规则:**

```nginx
location ^~ /usr/uploads/llmstxt/ {
    internal;
}

location ~ \.md$ {
    rewrite ^/(.*\.md)$ /usr/uploads/llmstxt/$1 last;
}
```

**Apache (.htaccess) 规则:**

```apache
RewriteEngine On
RewriteCond %{ENV:REDIRECT_STATUS} ^$
RewriteRule ^usr/uploads/llmstxt/ - [F]

RewriteCond %{REQUEST_URI} !^/usr/uploads/llmstxt/
RewriteRule ^(.*\.md)$ usr/uploads/llmstxt/$1 [L]
```

4. 服务器配置完成后，进入插件设置页面，点击 **强制重新生成缓存** 按钮完成初始化构建。

### 鸣谢

* 灵感与初始版本参考：[9bingyin](https://github.com/9bingyin/typecho-llmstxt)

---

## English Documentation

This plugin is developed based on the latest [llms.txt v2 specification](https://llmstxt.org/). It generates an LLM-friendly site structure for Typecho blogs, providing both a global index and clean Markdown versions of individual posts for AI agents (e.g., ChatGPT, Claude, Kimi).

### Key Features

* **Full v2 Spec Compliance**: Automatically generates the `/llms.txt` index and independent `.md` files for posts and pages.
* **Header Link Injection**: Automatically injects `<link rel="alternate">` and `rel="describedby"` tags into the `<head>` section.
* **Rewrite Isolation**: Physical static files are protected by server `internal` rules, preventing unauthorized direct access.

### Installation & Configuration

1. Rename the plugin folder to `LlmstxtV2` and upload it to the `usr/plugins/` directory.
2. Log in to the Typecho admin panel and activate the plugin.
3. **CRITICAL STEP**: You MUST apply the following rewrite rules to your Web Server to protect the physical paths and route the virtual `.md` requests:

**Nginx Rules:**

```nginx
location ^~ /usr/uploads/llmstxt/ {
    internal;
}

location ~ \.md$ {
    rewrite ^/(.*\.md)$ /usr/uploads/llmstxt/$1 last;
}
```

**Apache (.htaccess) Rules:**

```apache
RewriteEngine On
RewriteCond %{ENV:REDIRECT_STATUS} ^$
RewriteRule ^usr/uploads/llmstxt/ - [F]

RewriteCond %{REQUEST_URI} !^/usr/uploads/llmstxt/
RewriteRule ^(.*\.md)$ usr/uploads/llmstxt/$1 [L]
```

4. After configuring your server, go to the plugin settings page and click the **Force Rebuild Cache** button to initialize the files.

### Credits

* Inspiration & Initial Base: [9bingyin](https://github.com/9bingyin/typecho-llmstxt)
