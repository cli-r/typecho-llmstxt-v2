<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * llms.txt Generator v2
 *
 * @package LlmstxtV2
 * @author Nagi
 * @version 1.0.5
 * @link https://llmstxt.org/
 */
class LlmstxtV2_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     * 
     * @return string
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('LlmstxtV2_Plugin', 'generate');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('LlmstxtV2_Plugin', 'generate');
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishSave = array('LlmstxtV2_Plugin', 'generate');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishSave = array('LlmstxtV2_Plugin', 'generate');
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishDelete = array('LlmstxtV2_Plugin', 'generate');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishDelete = array('LlmstxtV2_Plugin', 'generate');

        Typecho_Plugin::factory('Widget_Archive')->header = array('LlmstxtV2_Plugin', 'injectHeaderLinks');

        // 插件激活时强制执行首次全量生成
        self::doGenerate(true, false);

        return '插件已激活。请务必前往插件设置页面查看并配置服务器伪静态规则。';
    }

    /**
     * 禁用插件
     * 
     * @return string
     */
    public static function deactivate()
    {
        $llmsTxtPath = __TYPECHO_ROOT_DIR__ . '/llms.txt';
        if (file_exists($llmsTxtPath)) {
            @unlink($llmsTxtPath);
        }

        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/llmstxt';
        self::deleteDirectory($cacheDir);

        return '插件已禁用，已清理所有生成的 llms.txt 和静态 MD 目录。';
    }

    /**
     * 获取插件配置面板
     * 
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $options = Typecho_Widget::widget('Widget_Options');

        // 拦截后台手动强制重新生成的请求
        if (isset($_GET['action']) && $_GET['action'] === 'force_generate_llms') {
            try {
                self::doGenerate(true, true);
                Typecho_Widget::widget('Widget_Notice')->set(_t('llms.txt 及静态 MD 缓存已成功强制重新生成。'), 'success');
            } catch (Exception $e) {
                Typecho_Widget::widget('Widget_Notice')->set(_t('生成失败: ' . $e->getMessage()), 'error');
            }
            $options->response->redirect(Typecho_Common::url('options-plugin.php?config=LlmstxtV2', $options->adminUrl));
            exit;
        }

        $triggerUrl = Typecho_Common::url('options-plugin.php?config=LlmstxtV2&action=force_generate_llms', $options->adminUrl);

        // 渲染 UI 与伪静态提示
        $rulesHtml = '<div style="color:#333;background:#E8F6FF;padding:15px;border-left:4px solid #467B96;margin-bottom:20px;">' .
            '<div style="float:right;">' .
            '<a href="' . $triggerUrl . '" style="background:#467B96;color:#fff;padding:6px 15px;text-decoration:none;border-radius:3px;font-size:13px;display:inline-block;box-shadow:0 1px 3px rgba(0,0,0,0.2);">强制重新生成缓存</a>' .
            '</div>' .
            '<h4 style="margin-top:0;"><strong>服务器伪静态配置（必填项）</strong></h4>' .
            '<p style="font-size:13px;line-height:1.5;">为避免访问 .md 文件时被 Typecho 拦截报 404 错误，请在环境配置文件中加入以下规则。<br><span style="color:#d33;">注意：插件的重写规则必须放置在 Typecho 默认路由规则之前！</span></p>' .
            
            '<strong>Nginx:</strong>' .
            '<pre style="background:#fff;padding:10px;border:1px solid #ccc;overflow-x:auto;margin-bottom:5px;">' .
            "location ^~ /usr/uploads/llmstxt/ {\n    internal;\n}\n\n" .
            "rewrite ^/(?!usr/uploads/llmstxt/)(.*\.md)$ /usr/uploads/llmstxt/$1 last;\n" .
            '</pre>' .
            '<details style="margin-bottom:15px;font-size:13px;cursor:pointer;"><summary style="color:#467B96;outline:none;">查看包含 Typecho 默认规则的完整 Nginx 示例（点击展开）</summary>' .
            '<pre style="background:#f9f9f9;padding:10px;border:1px dashed #ccc;overflow-x:auto;margin-top:5px;cursor:text;">' .
            "location ^~ /usr/uploads/llmstxt/ {\n    internal;\n}\n\n" .
            "rewrite ^/(?!usr/uploads/llmstxt/)(.*\.md)$ /usr/uploads/llmstxt/$1 last;\n\n" .
            "# --- 以下为 Typecho 默认伪静态 ---\n" .
            "if (!-e \$request_filename) {\n    rewrite ^(.*)$ /index.php\$1 last;\n}\n" .
            '</pre></details>' .
            
            '<strong>Apache (.htaccess):</strong>' .
            '<pre style="background:#fff;padding:10px;border:1px solid #ccc;overflow-x:auto;margin-bottom:5px;">' .
            "RewriteCond %{ENV:REDIRECT_STATUS} ^$\n" .
            "RewriteRule ^usr/uploads/llmstxt/ - [F]\n\n" .
            "RewriteCond %{REQUEST_URI} !^/usr/uploads/llmstxt/\n" .
            "RewriteRule ^(.*\.md)$ usr/uploads/llmstxt/$1 [L]\n" .
            '</pre>' .
            '<details style="font-size:13px;cursor:pointer;"><summary style="color:#467B96;outline:none;">查看包含 Typecho 默认规则的完整 Apache 示例（点击展开）</summary>' .
            '<pre style="background:#f9f9f9;padding:10px;border:1px dashed #ccc;overflow-x:auto;margin-top:5px;cursor:text;">' .
            "&lt;IfModule mod_rewrite.c&gt;\n" .
            "RewriteEngine On\n" .
            "RewriteBase /\n\n" .
            "RewriteCond %{ENV:REDIRECT_STATUS} ^$\n" .
            "RewriteRule ^usr/uploads/llmstxt/ - [F]\n\n" .
            "RewriteCond %{REQUEST_URI} !^/usr/uploads/llmstxt/\n" .
            "RewriteRule ^(.*\.md)$ usr/uploads/llmstxt/$1 [L]\n\n" .
            "# --- 以下为 Typecho 默认伪静态 ---\n" .
            "RewriteCond %{REQUEST_FILENAME} !-f\n" .
            "RewriteCond %{REQUEST_FILENAME} !-d\n" .
            "RewriteRule ^(.*)$ index.php [L]\n" .
            "&lt;/IfModule&gt;\n" .
            '</pre></details>' .
            '</div>';
        
        $ruleElement = new Typecho_Widget_Helper_Layout('div');
        $ruleElement->html($rulesHtml);
        $form->addItem($ruleElement);

        $siteDescription = new Typecho_Widget_Helper_Form_Element_Textarea('siteDescription', NULL, '', _t('网站描述'), _t('为 llms.txt 提供的描述，留空使用系统默认设置。'));
        $form->addInput($siteDescription);

        $limitPosts = new Typecho_Widget_Helper_Form_Element_Radio('limitPosts', array('0' => _t('不限制'), '1' => _t('限制')), '0', _t('限制文章数量'));
        $form->addInput($limitPosts);

        $postCount = new Typecho_Widget_Helper_Form_Element_Text('postCount', NULL, '10', _t('文章数量'), _t('仅在上方选择“限制”时生效。'));
        $form->addInput($postCount);

        $includePages = new Typecho_Widget_Helper_Form_Element_Radio('includePages', array('1' => _t('是'), '0' => _t('否')), '1', _t('包含独立页面'), _t('是否将独立页面编入索引。'));
        $form->addInput($includePages);

        $enableMdFiles = new Typecho_Widget_Helper_Form_Element_Radio('enableMdFiles', array('1' => _t('是'), '0' => _t('否')), '1', _t('生成独立 MD 文件 (v2 规范)'), _t('关闭此项则仅生成全局 llms.txt。建议保持开启以符合最新规范。'));
        $form->addInput($enableMdFiles);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 前台头部规范链接注入
     * 
     * @param string $header
     * @param Widget_Archive $archive
     * @return string
     */
    public static function injectHeaderLinks($header, $archive)
    {
        $siteUrl = Typecho_Widget::widget('Widget_Options')->siteUrl;
        $links = '<link rel="describedby" href="' . rtrim($siteUrl, '/') . '/llms.txt">';

        try {
            $pluginOptions = Typecho_Widget::widget('Widget_Options')->plugin('LlmstxtV2');
            if ($pluginOptions->enableMdFiles == '1' && ($archive->is('post') || $archive->is('page'))) {
                $mdUrl = self::getMdUrl($archive->permalink);
                $links .= "\n" . '<link rel="alternate" type="text/markdown" href="' . $mdUrl . '">';
            }
        } catch (Typecho_Plugin_Exception $e) {
            // 忽略未配置异常
        }

        return $header . "\n" . $links;
    }

    /**
     * 内容变更钩子回调（增量更新入口）
     * 
     * @param array|int|null $contents 
     * @param Typecho_Widget|null $widget 
     */
    public static function generate($contents = null, $widget = null)
    {
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $pluginOptions = clone $options->plugin('LlmstxtV2');
        } catch (Typecho_Plugin_Exception $e) {
            return;
        }

        $enableMdFiles = isset($pluginOptions->enableMdFiles) ? strval($pluginOptions->enableMdFiles) : '1';
        $cacheBaseDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/llmstxt/';

        $cid = null;
        if ($widget && isset($widget->cid)) {
            $cid = $widget->cid;
        } elseif (is_array($contents) && isset($contents['cid'])) {
            $cid = $contents['cid'];
        } elseif (is_numeric($contents)) {
            // 兼容 delete 钩子传入的可能是数字 cid 的情况[cite: 1]
            $cid = $contents; 
        }

        // 通过 cid 重新从数据库拉取完整数据，防止钩子数据缺失导致生成 index.md
        if ($enableMdFiles === '1' && $cid) {
            try {
                $db = Typecho_Db::get();
                $post = $db->fetchRow($db->select()->from('table.contents')->where('cid = ?', $cid)->limit(1));

                if ($post && $post['status'] === 'publish') {
                    $post['year'] = date('Y', $post['created']);
                    $post['month'] = date('m', $post['created']);
                    $post['day'] = date('d', $post['created']);
                    
                    if ($post['type'] === 'post') {
                        $category = $db->fetchRow($db->select('table.metas.slug')
                            ->from('table.metas')
                            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                            ->where('table.relationships.cid = ?', $post['cid'])
                            ->where('table.metas.type = ?', 'category')
                            ->order('table.metas.order', Typecho_Db::SORT_ASC)
                            ->limit(1));
                        
                        if ($category) {
                            $post['category'] = $category['slug'];
                            $post['directory'] = $category['slug'];
                        } else {
                            $post['category'] = 'default';
                            $post['directory'] = 'default';
                        }
                    }

                    $permalink = Typecho_Router::url($post['type'], $post, $options->index);
                    self::buildPhysicalMdFile($post['title'], $post['text'], $permalink, $options->siteUrl, $cacheBaseDir);
                }
            } catch (Exception $e) {
                error_log('LlmstxtV2 Incremental Build Error: ' . $e->getMessage());
            }
        }

        // 更新全局 llms.txt 索引
        self::doGenerate(false, false);
    }
    /**
     * 执行索引生成与静态资源重构逻辑
     *
     * @param bool $isFullRebuild 是否全量重建目录结构
     * @param bool $throwException 是否抛出异常信息至调用方
     * @throws Exception
     */
    private static function doGenerate($isFullRebuild = false, $throwException = false)
    {
        if ($isFullRebuild) {
            @set_time_limit(0);
            @ini_set('memory_limit', '256M');
        }

        try {
            $db = Typecho_Db::get();
            $options = Typecho_Widget::widget('Widget_Options');
            
            try {
                $pluginOptions = clone $options->plugin('LlmstxtV2');
            } catch (Typecho_Plugin_Exception $e) {
                // 环境降级处理机制（适用于初次激活）
                $pluginOptions = new Typecho_Config();
                $pluginOptions->siteDescription = '';
                $pluginOptions->limitPosts = '0';
                $pluginOptions->postCount = '10';
                $pluginOptions->includePages = '1';
                $pluginOptions->enableMdFiles = '1';
            }

            $siteTitle = htmlspecialchars($options->title);
            $siteDescription = !empty($pluginOptions->siteDescription) ? htmlspecialchars($pluginOptions->siteDescription) : htmlspecialchars($options->description);

            // 写入 UTF-8 BOM 标识符
            $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
            $content = $bom . "# {$siteTitle}\n\n";
            
            if (!empty($siteDescription)) {
                $content .= "> {$siteDescription}\n\n";
            }

            $limitPosts = isset($pluginOptions->limitPosts) ? strval($pluginOptions->limitPosts) : '0';
            $postCount = !empty($pluginOptions->postCount) ? intval($pluginOptions->postCount) : 10;
            $enableMdFiles = isset($pluginOptions->enableMdFiles) ? strval($pluginOptions->enableMdFiles) : '1';
            $cacheBaseDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/llmstxt/';

            // 初始化或清理基础缓存目录结构
            if ($isFullRebuild && $enableMdFiles === '1') {
                self::deleteDirectory($cacheBaseDir);
                if (!is_dir($cacheBaseDir)) {
                    if (!@mkdir($cacheBaseDir, 0755, true)) {
                        throw new Exception("无法创建目录 {$cacheBaseDir}，请检查系统读写权限。");
                    }
                }
            }

            $content .= "## 文章\n\n";
            
            // 数据分批处理配置
            $pageSize = 50; 
            $currentPage = 1;
            $processedCount = 0;
            $maxLimit = ($limitPosts === '1' && $postCount > 0) ? $postCount : PHP_INT_MAX;

            while (true) {
                if ($processedCount >= $maxLimit) {
                    break;
                }

                $currentBatchLimit = min($pageSize, $maxLimit - $processedCount);

                $select = $db->select()->from('table.contents')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.type = ?', 'post')
                    ->order('table.contents.created', Typecho_Db::SORT_DESC)
                    ->limit($currentBatchLimit)
                    ->offset(($currentPage - 1) * $pageSize);

                $posts = $db->fetchAll($select);

                if (empty($posts)) {
                    break;
                }

                foreach ($posts as $post) {
                    $post['year'] = date('Y', $post['created']);
                    $post['month'] = date('m', $post['created']);
                    $post['day'] = date('d', $post['created']);

                    $category = $db->fetchRow($db->select('table.metas.slug')
                        ->from('table.metas')
                        ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                        ->where('table.relationships.cid = ?', $post['cid'])
                        ->where('table.metas.type = ?', 'category')
                        ->order('table.metas.order', Typecho_Db::SORT_ASC)
                        ->limit(1));
                    
                    if ($category) {
                        $post['category'] = $category['slug'];
                        $post['directory'] = $category['slug'];
                    } else {
                        $post['category'] = 'default';
                        $post['directory'] = 'default';
                    }

                    $permalink = Typecho_Router::url('post', $post, $options->index);
                    $title = htmlspecialchars($post['title']);
                    $excerpt = self::getPostExcerpt($post['text']);
                    $mdUrl = self::getMdUrl($permalink);
                    
                    $content .= "- [{$title}]({$mdUrl}): {$excerpt}\n";

                    if ($isFullRebuild && $enableMdFiles === '1') {
                        self::buildPhysicalMdFile($post['title'], $post['text'], $permalink, $options->siteUrl, $cacheBaseDir);
                    }
                    $processedCount++;
                }

                $currentPage++;
            }

            if ($processedCount === 0) {
                $content .= "- 暂无文章\n";
            }

            // 页面类型数据处理逻辑
            if (!empty($pluginOptions->includePages) && $pluginOptions->includePages == '1') {
                $content .= "\n## 页面\n\n";
                $pages = $db->fetchAll($db->select()->from('table.contents')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.type = ?', 'page')
                    ->order('table.contents.order', Typecho_Db::SORT_ASC));

                foreach ($pages as $page) {
                    $permalink = Typecho_Router::url('page', $page, $options->index);
                    $title = htmlspecialchars($page['title']);
                    $excerpt = self::getPostExcerpt($page['text']);
                    $mdUrl = self::getMdUrl($permalink);
                    
                    $content .= "- [{$title}]({$mdUrl}): {$excerpt}\n";

                    if ($isFullRebuild && $enableMdFiles === '1') {
                        self::buildPhysicalMdFile($page['title'], $page['text'], $permalink, $options->siteUrl, $cacheBaseDir);
                    }
                }
            }

            // 写入根目录 llms.txt 文件
            $llmsTxtPath = __TYPECHO_ROOT_DIR__ . '/llms.txt';
            if (@file_put_contents($llmsTxtPath, $content, LOCK_EX) === false) {
                throw new Exception("根目录索引文件 llms.txt 写入失败。");
            }

        } catch (Exception $e) {
            if ($throwException) {
                throw $e;
            }
            error_log('LlmstxtV2 Error: ' . $e->getMessage());
        }
    }

    /**
     * 构建独立的物理 Markdown 文件
     * 
     * @param string $title
     * @param string $text
     * @param string $permalink
     * @param string $siteUrl
     * @param string $cacheBaseDir
     * @throws Exception
     */
    private static function buildPhysicalMdFile($title, $text, $permalink, $siteUrl, $cacheBaseDir)
    {
        $parsedUrl = parse_url($permalink);
        $siteParsedUrl = parse_url($siteUrl);
        $sitePath = isset($siteParsedUrl['path']) ? rtrim($siteParsedUrl['path'], '/') : '';
        $relativePath = isset($parsedUrl['path']) ? ltrim(str_replace($sitePath, '', $parsedUrl['path']), '/') : '';

        if (preg_match('/\.([a-zA-Z0-9]+)$/', $relativePath)) {
            $relativePath .= '.md';
        } else {
            $relativePath = rtrim($relativePath, '/') . '/index.md';
        }
        // 解码与过滤处理：防范目录穿越漏洞
        $relativePath = urldecode($relativePath);
        $relativePath = str_replace(array('../', '..\\'), '', $relativePath);
        
        $physicalPath = $cacheBaseDir . $relativePath;
        $dir = dirname($physicalPath);
        
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                 throw new Exception("无法创建目录路径: {$dir}");
            }
        }

        $cleanText = preg_replace('/^<!--markdown-->\s*/', '', $text);
        // 追加 UTF-8 BOM 以保障浏览器原生查看的编码正确性
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $mdContent = $bom . "# {$title}\n\n" . $cleanText;

        if (@file_put_contents($physicalPath, $mdContent, LOCK_EX) === false) {
             throw new Exception("无法写入数据至文件: {$physicalPath}");
        }
    }

    /**
     * 生成规范的 Markdown 后缀链接
     * 
     * @param string $permalink
     * @return string
     */
    private static function getMdUrl($permalink)
    {
        return rtrim($permalink, '/') . (preg_match('/\.([a-zA-Z0-9]+)$/', $permalink) ? '.md' : '/index.md');
    }

    /**
     * 递归删除目录节点
     * 
     * @param string $dir
     */
    private static function deleteDirectory($dir)
    {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            $path = "$dir/$file";
            (is_dir($path)) ? self::deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * 提取文章摘要文本
     * 
     * @param string $text
     * @return string
     */
    private static function getPostExcerpt($text)
    {
        $text = preg_replace('/<!--more-->.*$/s', '', $text);
        $text = preg_replace('/```[\s\S]*?```/u', '', $text);
        $text = strip_tags($text);
        $text = preg_replace('/!\[.*?\]\(.*?\)/u', '', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/u', '$1', $text);
        $text = preg_replace('/^\s*#{1,6}\s+/mu', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        return empty($text) ? '暂无摘要' : (mb_strlen($text, 'UTF-8') <= 100 ? $text : mb_substr($text, 0, 100, 'UTF-8') . '...');
    }
}
