import { defineConfig, createLogger } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const logger = createLogger();
const originalWarn = logger.warn.bind(logger);
logger.warn = (msg, options) => {
    if (msg.includes("didn't resolve at build time")) return;
    originalWarn(msg, options);
};

function legacyCss() {
    return {
        name: 'legacy-css',
        resolveId(id) {
            if (id.startsWith('/assets/images/')) {
                const path = new URL(`../public${id}`, import.meta.url).pathname;
                return { id: path, external: true };
            }
        },
        transform(code, id) {
            if (!id.endsWith('.css')) return;
            if (id.includes('app.min.css') || id.includes('theme.css')) {
                code = code.replace(/@charset\s+["']UTF-8["'];?\s*/g, '');
                code = code.replace(/@import\s+url\([^)]*googleapis\.com[^)]*\);?\s*/g, '');
            }
            code = code.replace(/url\((['"]?)\.\.\/images\/users\//g, "url($1/assets/images/users/");
            code = code.replace(/url\((['"]?)\/assets\/images\/users\/([^)'"]+)\1\)/g,
                (_, q, file) => `url(${q || ''}/assets/images/users/${file}?static${q || ''})`);
            return { code, map: null };
        },
    };
}

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

            if (id.includes('lib/jquery')) {
                code = code.replace(/\(this\.jQuery=this\.\$=/g, '(window.jQuery=window.$=');
            }

            if (id.includes('lib/vendor')) {
                code = code.replace(/\}\)\(this,/g, '})(window,');
                code = code.replace(/\}\(this,/g, '}(window,');
            }

            code = code.replace(/\/\/# sourceMappingURL=\S+/g, '');

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
