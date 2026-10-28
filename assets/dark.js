/* ============================================================================
   AdminDark —— 后台夜间模式的切换按钮、配色切换与状态同步
   ----------------------------------------------------------------------------
   状态存在 cookie（不是 localStorage），因为服务端要能在首次渲染时就定色。
   本脚本负责：插入「暗色/亮色」与「配色」两个控件、切换、写回 cookie、
   跟随系统偏好变化。
   ============================================================================ */

(function () {
    'use strict';

    var cfg = window.AdminDarkConfig;

    if (!cfg) {
        return;
    }

    var root = document.documentElement;
    var ATTR = cfg.attr;
    var COOKIE = cfg.cookie;
    var COOKIE_SCHEME = cfg.schemeCookie || 'admin_dark_scheme';
    var LABELS = cfg.modes || { auto: '自动', light: '亮色', dark: '暗色' };
    var ICONS = { auto: '🌗', light: '☀', dark: '🌙' };
    var SCHEMES = cfg.schemes || {};

    /* 点击循环顺序：自动 → 亮色 → 暗色 → 自动 */
    var ORDER = ['auto', 'light', 'dark'];

    var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    /* ---------------- cookie 读写 ---------------- */

    function cookieRead(name, allowed, fallback) {
        var hit = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
        var value = hit ? decodeURIComponent(hit[1]) : '';

        return allowed.indexOf(value) >= 0 ? value : fallback;
    }

    function cookieWrite(name, value) {
        var secure = 'https:' === location.protocol ? '; Secure' : '';

        document.cookie = name + '=' + value + '; path=/; max-age=31536000; SameSite=Lax' + secure;
    }

    function readChoice() {
        return cookieRead(COOKIE, ORDER, 'auto');
    }

    /* ---------------- 模式 ---------------- */

    function effectiveOf(choice) {
        if ('auto' === choice) {
            return (mq && mq.matches) ? 'dark' : 'light';
        }

        return choice;
    }

    var modeBtn = null;
    var modeIcon = null;
    var modeText = null;

    function paintMode(choice, effective) {
        if (!modeBtn) {
            return;
        }

        modeIcon.textContent = ICONS[choice] || ICONS.auto;
        modeText.textContent = LABELS[choice] || choice;

        modeBtn.title = '当前：' + (LABELS[choice] || choice)
            + '（实际显示' + ('dark' === effective ? '深色' : '浅色') + '）—— 点击切换';
        modeBtn.setAttribute('aria-label', modeBtn.title);
    }

    function applyMode(choice) {
        var effective = effectiveOf(choice);

        root.setAttribute(ATTR, effective);
        root.setAttribute(ATTR + '-choice', choice);

        paintMode(choice, effective);
    }

    /* ---------------- 配色 ---------------- */

    var schemeItems = [];

    function readScheme() {
        var keys = Object.keys(SCHEMES);

        return cookieRead(COOKIE_SCHEME, keys, cfg.defaultScheme || keys[0] || 'graphite');
    }

    function applyScheme(key) {
        if (!SCHEMES[key]) {
            return;
        }

        root.setAttribute('data-admin-scheme', key);
        cookieWrite(COOKIE_SCHEME, key);

        schemeItems.forEach(function (item) {
            item.classList.toggle('is-current', item.dataset.scheme === key);
            item.setAttribute('aria-current', item.dataset.scheme === key ? 'true' : 'false');
        });
    }

    function swatchMarkup(scheme) {
        return '<span class="ad-swatch" style="background:' + scheme.bg
            + ';border-color:' + (scheme.surface || 'transparent') + '">'
            + '<i style="background:' + scheme.accent + '"></i></span>';
    }

    function buildSchemeMenu() {
        var wrap = document.createElement('div');
        wrap.className = 'ad-scheme';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ad-switch ad-scheme-btn';
        btn.setAttribute('aria-haspopup', 'true');
        btn.setAttribute('aria-expanded', 'false');
        btn.title = cfg.schemeLabel || '配色';

        var icon = document.createElement('span');
        icon.className = 'ad-switch-ico';
        icon.textContent = '🎨';

        var text = document.createElement('span');
        text.textContent = cfg.schemeLabel || '配色';

        btn.appendChild(icon);
        btn.appendChild(text);

        var menu = document.createElement('ul');
        menu.className = 'ad-scheme-menu';

        Object.keys(SCHEMES).forEach(function (key) {
            var scheme = SCHEMES[key];

            var li = document.createElement('li');
            var item = document.createElement('button');

            item.type = 'button';
            item.className = 'ad-scheme-item';
            item.dataset.scheme = key;
            item.innerHTML = swatchMarkup(scheme) + '<span>' + scheme.label + '</span>';

            item.addEventListener('click', function () {
                applyScheme(key);
                closeMenu();
            });

            schemeItems.push(item);
            li.appendChild(item);
            menu.appendChild(li);
        });

        function closeMenu() {
            wrap.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', function (event) {
            event.stopPropagation();

            var open = wrap.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
            if (!wrap.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key) {
                closeMenu();
            }
        });

        wrap.appendChild(btn);
        wrap.appendChild(menu);

        return wrap;
    }

    /* ---------------- 挂载 ---------------- */

    function buildModeButton() {
        modeBtn = document.createElement('button');
        modeBtn.type = 'button';
        modeBtn.id = 'AdminDarkSwitch';
        modeBtn.className = 'ad-switch';

        modeIcon = document.createElement('span');
        modeIcon.className = 'ad-switch-ico';

        modeText = document.createElement('span');

        modeBtn.appendChild(modeIcon);
        modeBtn.appendChild(modeText);

        modeBtn.addEventListener('click', function () {
            var next = ORDER[(ORDER.indexOf(readChoice()) + 1) % ORDER.length];

            cookieWrite(COOKIE, next);
            applyMode(next);
        });

        return modeBtn;
    }

    function mount(nodes) {
        var operate = document.querySelector('.typecho-head-nav nav > menu > li.operate');

        if ('nav' === cfg.pos && operate) {
            var li = document.createElement('li');
            li.className = 'ad-switch-li';

            nodes.forEach(function (node) {
                li.appendChild(node);
            });

            operate.insertBefore(li, operate.firstChild);

            return;
        }

        /* 导航不存在（比如登录页），或配置成悬浮：竖着叠在右下角。
           倒着排：模式按钮贴最下面，配色按钮在它上方 —— 这样配色菜单
           向上展开时不会盖住模式按钮。 */
        nodes.forEach(function (node, index) {
            node.classList.add('ad-float');
            /* 「配色」是一整个 div（它的按钮在内部），不能只看 node 自己有没有 .ad-switch */
            node.style.bottom = (22 + (nodes.length - 1 - index) * 46) + 'px';
            document.body.appendChild(node);
        });
    }

    /* ---------------- 启动 ---------------- */

    function init() {
        var nodes = [];

        /* 按钮可以关掉，但肤色照样要生效 */
        if (cfg.show) {
            nodes.push(buildSchemeMenu());
            nodes.push(buildModeButton());
        }

        applyMode(readChoice());
        applyScheme(readScheme());

        if (nodes.length) {
            mount(nodes);
        }

        /* 选了「自动」时要跟着系统变 */
        if (mq) {
            var onSystemChange = function () {
                if ('auto' === readChoice()) {
                    applyMode('auto');
                }
            };

            if (mq.addEventListener) {
                mq.addEventListener('change', onSystemChange);
            } else if (mq.addListener) {
                mq.addListener(onSystemChange);
            }
        }
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
