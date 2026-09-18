<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 *
 * 给 Typecho 后台（/admin/）加一套深色皮肤。
 *
 * @package AdminDark
 * @author  zizdog
 * @version 0.0.1
 * @link    https://zizdog.com
 */

// 兼容 1.2 / 1.3 两套类名
if (!interface_exists('Typecho_Plugin_Interface')) {
    @class_alias('Typecho\\Plugin\\PluginInterface', 'Typecho_Plugin_Interface');
}

class AdminDark_Plugin implements Typecho_Plugin_Interface
{
    /** 存「用户选择」的 cookie 名，值是 auto / light / dark */
    const COOKIE = 'admin_dark';

    /** 深色样式挂载点属性名 */
    const ATTR = 'data-admin-dark';

    /** 可选模式 */
    const MODES = array('auto', 'light', 'dark');

    /** 存配色方案的 cookie 名 */
    const COOKIE_SCHEME = 'admin_dark_scheme';

    /**
     * 深色档的配色方案
     *
     * 这里是唯一数据源：PHP 侧用来生成设置项与导航里的切换菜单，
     * 具体的色值写在 assets/dark.css 里（html[data-admin-scheme="x"]）。
     * 加一套新配色 = 这里加一行 + dark.css 加一段。
     *
     * bg / surface / accent 只用于界面上画那个小色卡。
     */
    const SCHEMES = array(
        'graphite' => array('label' => '石墨',   'bg' => '#1b1e23', 'surface' => '#242830', 'accent' => '#5d9cbf'),
        'midnight' => array('label' => '午夜蓝', 'bg' => '#12161d', 'surface' => '#1a212b', 'accent' => '#4f9bd8'),
        'plum'     => array('label' => '深紫',   'bg' => '#1a1520', 'surface' => '#241d2c', 'accent' => '#a37bd8'),
        'forest'   => array('label' => '松林',   'bg' => '#131b17', 'surface' => '#1c2620', 'accent' => '#5fb98c'),
        'ember'    => array('label' => '暖褐',   'bg' => '#201a15', 'surface' => '#2a231c', 'accent' => '#d4915c'),
    );

    /** 默认配色 */
    const SCHEME_DEFAULT = 'graphite';

    /* ================================================================ */
    /* 生命周期                                                          */
    /* ================================================================ */

    /**
     * 启用插件
     *
     * Typecho 会把这里注册的钩子写进 options.plugins，每次请求自动还原。
     */
    public static function activate()
    {
        $plugin = self::pluginClass();

        // head：样式 + 首屏定色脚本
        $plugin::factory('admin/header.php')->header = array(__CLASS__, 'header');

        // 页脚：切换按钮 + 交互脚本
        $plugin::factory('admin/footer.php')->end = array(__CLASS__, 'footer');

        return _t('后台夜间模式已启用。页面右上角（或右下角）会出现「自动 / 亮色 / 暗色」切换按钮。');
    }

    /**
     * 停用插件
     */
    public static function deactivate()
    {
        // 只解除钩子，不动任何数据；用户的选择留在 cookie 里，重新启用即恢复
    }

    /* ================================================================ */
    /* 插件设置                                                          */
    /* ================================================================ */

    /**
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $radio = self::elementClass('Radio');
        $text = self::elementClass('Text');
        $textarea = self::elementClass('Textarea');

        $form->addInput(new $radio(
            'mode',
            array('auto' => _t('跟随系统'), 'light' => _t('浅色'), 'dark' => _t('深色')),
            'auto',
            _t('默认模式'),
            _t('第一次访问（还没有 cookie）时用哪种。选「跟随系统」则由浏览器 / 操作系统的深色偏好决定。')
        ));

        $form->addInput(new $radio(
            'button',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('切换按钮'),
            _t('隐藏后仍然会按上面的默认模式生效，只是页面上不出现按钮。')
        ));

        // 配色方案：直接列出每一套，前面用色卡做视觉区分
        $schemeOptions = array();

        foreach (self::SCHEMES as $key => $scheme) {
            $schemeOptions[$key] = '<span class="ad-swatch" style="'
                . 'background:' . htmlspecialchars($scheme['bg'], ENT_QUOTES, 'UTF-8') . ';'
                . 'border-color:' . htmlspecialchars($scheme['surface'], ENT_QUOTES, 'UTF-8') . '">'
                . '<i style="background:' . htmlspecialchars($scheme['accent'], ENT_QUOTES, 'UTF-8') . '"></i>'
                . '</span>' . htmlspecialchars($scheme['label'], ENT_QUOTES, 'UTF-8');
        }

        $scheme = new $radio(
            'scheme',
            $schemeOptions,
            self::SCHEME_DEFAULT,
            _t('配色方案'),
            _t('深色档用哪套配色。导航栏里的「配色」按钮可以随时临时切换（只影响你自己）；'
                . '这里选的是默认值，对还没选过的人生效。')
        );
        $scheme->setAttribute('class', 'ad-scheme-radio');
        $form->addInput($scheme);

        $form->addInput(new $radio(
            'position',
            array('nav' => _t('顶部导航右侧'), 'float' => _t('右下角悬浮')),
            'nav',
            _t('按钮位置'),
            _t('「顶部导航右侧」和后台原有菜单同一行；「右下角悬浮」固定在视口右下角。')
        ));

        $form->addInput(new $text(
            'darkAccent',
            null,
            '#5d9cbf',
            _t('深色模式强调色'),
            _t('链接、主按钮、选中态的强调色。留空则用内置默认值。')
        ));

        $form->addInput(new $textarea(
            'customCss',
            null,
            '',
            _t('自定义深色 CSS'),
            _t('会追加在内置深色样式之后，用来微调个别地方。不要写 &lt;style&gt; 标签，直接写规则即可。')
        ));
    }

    /**
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // 本插件没有「个人设置」这一层
    }

    /* ================================================================ */
    /* 输出                                                              */
    /* ================================================================ */

    /**
     * 往后台 <head> 里塞样式与首屏定色脚本
     *
     * 钩子契约（Typecho\Plugin::filter）回调用返回值替换原内容，
     * 所以这里必须把传进来的 $header 原样带上。
     *
     * @param string $header
     * @return string
     */
    public static function header($header)
    {
        $attr = json_encode(self::ATTR);
        $choice = json_encode(self::currentSet());   // 必须 json_encode，否则裸字符串会写进 JS 变语法错误
        $scheme = json_encode(self::currentScheme());

        $css = self::assetUrl('dark.css');
        $ver = self::assetVersion('dark.css');

        // 【顺序很重要】先脚本后样式：
        // 脚本在 <head> 解析阶段同步执行，此时 body 还没开始渲染，
        // 因此属性一定在首次绘制之前就位，不会闪白。
        $out = '<script>(function(){'
            . 'var c=' . $choice . ', a=' . $attr . ', s=' . $scheme . ', m=c;'
            . 'try{'
            . 'if(m==="auto"){'
            . 'm=(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches)?"dark":"light";'
            . '}'
            . '}catch(e){m="light";}'
            . 'var d=document.documentElement;'
            . 'd.setAttribute(a,m);'
            . 'd.setAttribute(a+"-choice",c);'
            . 'd.setAttribute("data-admin-scheme",s);'
            . '})();</script>' . "\n";

        $out .= '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8')
            . ($ver ? '?v=' . $ver : '') . '">' . "\n";

        // 自定义 CSS 直接内联，省一次请求
        $custom = trim((string) self::opt('customCss', ''));

        if ('' !== $custom) {
            // 防止有人把 </style> 写进来截断标签
            $custom = str_ireplace('</style', '<\/style', $custom);
            $out .= '<style id="admin-dark-custom">' . $custom . '</style>' . "\n";
        }

        $accent = trim((string) self::opt('darkAccent', ''));

        if ('' !== $accent && preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) {
            $out .= '<style>html[' . self::ATTR . '="dark"]{--ad-accent:' . $accent . ';}</style>' . "\n";
        }

        return $header . "\n" . $out;
    }

    /**
     * 页脚：切换按钮与交互脚本
     *
     * @return void
     */
    public static function footer()
    {
        $show = '1' === (string) self::opt('button', '1');
        $pos  = (string) self::opt('position', 'nav');

        if (!in_array($pos, array('nav', 'float'), true)) {
            $pos = 'nav';
        }

        $cfg = array(
            'cookie' => self::COOKIE,
            'schemeCookie' => self::COOKIE_SCHEME,
            'attr'   => self::ATTR,
            'show'   => $show,
            'pos'    => $pos,
            'modes'  => array(
                'auto'  => _t('自动'),
                'light' => _t('亮色'),
                'dark'  => _t('暗色'),
            ),
            'schemes' => self::SCHEMES,
            'defaultScheme' => self::currentScheme(),
            'schemeLabel' => _t('配色'),
        );

        echo '<script>window.AdminDarkConfig=' . json_encode($cfg, JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
        echo '<script src="' . htmlspecialchars(self::assetUrl('dark.js'), ENT_QUOTES, 'UTF-8')
            . (self::assetVersion('dark.js') ? '?v=' . self::assetVersion('dark.js') : '') . '"></script>' . "\n";
    }

    /* ================================================================ */
    /* 内部工具                                                          */
    /* ================================================================ */

    /**
     * 拿到 \Typecho\Plugin 或旧名，两边都能用
     *
     * @return string
     */
    protected static function pluginClass()
    {
        return class_exists('Typecho\\Plugin') ? 'Typecho\\Plugin' : 'Typecho_Plugin';
    }

    /**
     * 兼容 1.2 / 1.3 的表单元素类名
     *
     * @param string $type
     * @return string
     */
    protected static function elementClass($type)
    {
        $candidates = array(
            'Typecho\\Widget\\Helper\\Form\\Element\\' . $type,
            'Typecho_Widget_Helper_Form_Element_' . $type,
        );

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return $candidates[0];
    }

    /**
     * 读插件配置
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected static function opt($key, $default = null)
    {
        try {
            $options = Helper::options();
            $config = $options->plugin('AdminDark');

            if (is_object($config) && isset($config->{$key})) {
                $value = $config->{$key};

                return (null === $value || '' === $value) ? $default : $value;
            }
        } catch (Throwable $e) {
            // 插件还没配置过就走默认值
        }

        return $default;
    }

    /**
     * 用户明确选过的那一档（cookie），没选过返回默认模式
     *
     * @return string auto|light|dark
     */
    protected static function currentSet()
    {
        $raw = isset($_COOKIE[self::COOKIE]) ? strtolower(trim((string) $_COOKIE[self::COOKIE])) : '';

        if (in_array($raw, self::MODES, true)) {
            return $raw;
        }

        $default = strtolower(trim((string) self::opt('mode', 'auto')));

        return in_array($default, self::MODES, true) ? $default : 'auto';
    }

    /**
     * 当前配色方案
     *
     * 优先级：cookie（用户在导航里临时切过）> 插件设置里的默认值 > 内置默认
     *
     * @return string
     */
    protected static function currentScheme()
    {
        $raw = isset($_COOKIE[self::COOKIE_SCHEME]) ? strtolower(trim((string) $_COOKIE[self::COOKIE_SCHEME])) : '';

        if (isset(self::SCHEMES[$raw])) {
            return $raw;
        }

        $default = strtolower(trim((string) self::opt('scheme', self::SCHEME_DEFAULT)));

        return isset(self::SCHEMES[$default]) ? $default : self::SCHEME_DEFAULT;
    }

    /**
     * 服务端能确定的那一档
     *
     * 只有 cookie 明确写了 light / dark 才能定死；auto 得交给浏览器，
     * 由 head 里那段脚本用 prefers-color-scheme 现场判断。
     *
     * @return string
     */
    protected static function resolveMode()
    {
        return self::currentSet();
    }

    /**
     * 静态资源地址
     *
     * @param string $file
     * @return string
     */
    protected static function assetUrl($file)
    {
        return rtrim(Helper::options()->pluginUrl, '/') . '/AdminDark/assets/' . ltrim($file, '/');
    }

    /**
     * 用文件修改时间当版本号，改完样式不会吃缓存
     *
     * @param string $file
     * @return string
     */
    protected static function assetVersion($file)
    {
        $path = __DIR__ . '/assets/' . ltrim($file, '/');

        return is_file($path) ? (string) filemtime($path) : '';
    }
}
