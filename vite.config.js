import { defineConfig, createLogger } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Suppress known harmless warnings from pre-built vendor CSS files
const logger = createLogger();
const originalWarn = logger.warn.bind(logger);
logger.warn = (msg, options) => {
    // Suppress: absolute public paths in pre-built CSS that can't be resolved at build time
    // but resolve correctly at runtime from the public/ directory
    if (msg.includes("didn't resolve at build time")) return;
    originalWarn(msg, options);
};

/**
 * legacyCss plugin:
 * Fixes pre-built minified CSS files before Vite processes them:
 *  1. app.min.css: strips `@charset` and Google Fonts `@import` (served via <link> in blade instead)
 *  2. Rewrites `url(../images/users/` → `url(/assets/images/users/` so runtime paths resolve correctly
 */
function legacyCss() {
    return {
        name: 'legacy-css',
        // Resolve absolute CSS asset paths to the public/ directory so Vite can find them
        resolveId(id) {
            if (id.startsWith('/assets/images/')) {
                const path = new URL(`../public${id}`, import.meta.url).pathname;
                return { id: path, external: true };
            }
        },
        transform(code, id) {
            if (!id.endsWith('.css')) return;
            // Fix 1: strip @charset and Google Fonts @import from pre-built CSS files
            if (id.includes('app.min.css') || id.includes('theme.css')) {
                code = code.replace(/@charset\s+["']UTF-8["'];?\s*/g, '');
                code = code.replace(/@import\s+url\([^)]*googleapis\.com[^)]*\);?\s*/g, '');
            }
            // Fix 2: rewrite relative image paths that won't resolve in build output
            // Add ?static query to prevent Vite from trying to resolve/hash the asset
            code = code.replace(/url\((['"]?)\.\.\/images\/users\//g, "url($1/assets/images/users/");
            // Prevent Vite from trying to process these absolute public-dir image URLs
            code = code.replace(/url\((['"]?)\/assets\/images\/users\/([^)'"]+)\1\)/g,
                (_, q, file) => `url(${q || ''}/assets/images/users/${file}?static${q || ''})`);
            return { code, map: null };
        },
    };
}

/**
 * legacyLibs plugin:
 * These UMD files have `module.exports` patterns that Rollup detects as CJS,
 * causing them to be wrapped in a lazy `__commonJS` factory (never executed for side-effect imports).
 *
 * Fix strategy per file:
 *  1. Prepend `var define,module,exports;` → UMD falls to browser-global branch (no require() calls)
 *  2. Append `export {}` → marks file as ES module, Rollup skips the lazy CJS wrapping
 *  3. jQuery extra: replace `this.jQuery=this.$=` with `window.jQuery=window.$=` (strict-mode fix)
 */
function legacyLibs() {
    const libFiles = [
        'lib/config', 'lib/jquery', 'lib/select2', 'lib/izitoast', 'lib/iziToast',
        'lib/sweetalert2', 'lib/customSweetalert2', 'lib/custom-sweetalert2', 'lib/jquery.validate',
        'lib/vendor', 'lib/theme-app',
    ];

    return {
        name: 'legacy-libs',
        transform(code, id) {
            if (!libFiles.some(f => id.includes(f))) return;

            // jQuery: its UMD uses `this.jQuery=this.$=` which is undefined in strict mode
            if (id.includes('lib/jquery')) {
                code = code.replace(/\(this\.jQuery=this\.\$=/g, '(window.jQuery=window.$=');
            }

            // vendor.js: Toastify and other libs use `})(this, ...)` — undefined in strict mode
            // moment.js uses `}(this, ...)` — also undefined in strict mode
            if (id.includes('lib/vendor')) {
                code = code.replace(/\}\)\(this,/g, '})(window,');
                code = code.replace(/\}\(this,/g, '}(window,');
            }

            // Wrap: disable CJS/AMD so UMD falls to browser-global branch
            // `export {}` prevents Rollup from lazy-wrapping as __commonJS
            return {
                code: `var define=undefined,module=undefined,exports=undefined;\n${code}\nexport {};`,
                map: null,
            };
        },
    };
}

export default defineConfig({
    customLogger: logger,
    plugins: [
        legacyCss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pages/roles/index.js',
                'resources/js/pages/roles/create.js',
                'resources/js/pages/roles/edit.js',
                'resources/js/pages/users/script.js',
                'resources/js/pages/selling-chart/script.js',
                'resources/js/pages/selling-chart/fabrication.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        legacyLibs(),
    ],
    build: {
        chunkSizeWarningLimit: 2500,
        rollupOptions: {
            onwarn(warning, warn) {
                // Suppress Vite's "asset not resolved at build time" for CSS URLs that
                // are intentionally absolute paths served from the public/ directory.
                if (warning.message && warning.message.includes("didn't resolve at build time")) return;
                warn(warning);
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
