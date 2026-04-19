import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

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
    plugins: [
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
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
