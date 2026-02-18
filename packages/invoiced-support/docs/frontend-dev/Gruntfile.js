module.exports = function (grunt) {
    var modRewrite = require('connect-modrewrite');
    var serveStatic = require('serve-static');
    require('load-grunt-tasks')(grunt);

    var JUI_THEME = 'base';
    var CODEMIRROR_THEME = 'monokai';
    var LIVE_RELOAD_PORT = 35728;

    let apiBaseUrl = '';
    if (typeof process.env.APP_DOMAIN !== 'undefined' && typeof process.env.CLOUDFLARE_DOMAIN !== 'undefined') {
        apiBaseUrl = 'https://' + process.env.APP_DOMAIN + '.' + process.env.CLOUDFLARE_DOMAIN + '/api';
    }

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        clean: {
            build: {
                src: ['build/'],
            },
            temp: {
                src: ['tmp/'],
                force: true,
            },
        },
        bower_concat: {
            all: {
                dest: {
                    js: 'tmp/js/vendor.bower.js',
                    css: 'tmp/css/vendor.bower.css',
                },
                bowerOptions: {
                    relative: false,
                },
                dependencies: {
                    angular: 'jquery',
                    bootstrap: 'jquery-ui',
                },
                mainFiles: {
                    jquery: [],
                    angular: [],
                    'blueimp-file-upload': [
                        'css/jquery.fileupload.css',
                        'js/cors/jquery.postmessage-transport.js',
                        'js/cors/jquery.xdr-transport.js',
                        'js/jquery.fileupload.js',
                        'js/jquery.fileupload-process.js',
                        'js/jquery.iframe-transport.js',
                        'js/jquery.fileupload-angular.js',
                    ],
                    vex: ['css/vex.css', 'css/vex-theme-default.css', 'js/vex.js', 'js/vex.dialog.js'],
                    'kennethkalmer-snapjs': ['snap.js', 'snap.css'],
                    'jquery-ui': ['jquery-ui.js', 'themes/' + JUI_THEME + '/jquery-ui.css'],
                    codemirror: [
                        'lib/codemirror.js',
                        'addon/selection/active-line.js',
                        'addon/edit/matchbrackets.js',
                        'addon/mode/multiplex.js',
                        'addon/mode/overlay.js',
                        'addon/mode/simple.js',
                        'addon/hint/show-hint.js',
                        'addon/hint/css-hint.js',
                        'mode/css/css.js',
                        'mode/xml/xml.js',
                        'mode/javascript/javascript.js',
                        'lib/codemirror.css',
                        'theme/' + CODEMIRROR_THEME + '.css',
                    ],
                },
            },
        },
        less: {
            app: {
                files: {
                    'tmp/css/app.css': 'less/styles.less',
                },
            },
        },
        useminPrepare: {
            html: 'src/index.html',
            options: {
                dest: 'tmp',
            },
        },
        copy: {
            staticFiles: {
                expand: true,
                cwd: 'static/',
                src: ['**'],
                dest: 'tmp/',
            },
            appIndex: {
                expand: true,
                cwd: 'src/',
                src: ['index.html'],
                dest: 'tmp/',
            },
            vendor: {
                files: [
                    {
                        expand: true,
                        cwd: 'components/jquery-ui/themes/' + JUI_THEME,
                        src: 'images/**',
                        dest: 'tmp/css/',
                    },
                    {
                        expand: true,
                        cwd: 'components/select2/',
                        src: ['select2-spinner.gif', 'select2.png', 'select2x2.png'],
                        dest: 'tmp/css/',
                    },
                ],
            },
            build: {
                expand: true,
                cwd: 'tmp/',
                src: ['**'],
                dest: 'build/',
            },
        },
        ngtemplates: {
            app: {
                cwd: 'src/',
                src: '**/*.html',
                dest: 'tmp/js/app.templates.js',
                options: {
                    htmlmin: {
                        collapseBooleanAttributes: true,
                        collapseWhitespace: false,
                        removeAttributeQuotes: true,
                        removeComments: true, // Only if you don't use comment directives!
                        removeEmptyAttributes: true,
                        removeRedundantAttributes: true,
                        removeScriptTypeAttributes: true,
                        removeStyleLinkTypeAttributes: true,
                    },
                },
            },
        },
        concat: {
            app_js: {
                options: {
                    separator: ';',
                },
                src: [
                    // include configuration
                    'config.js',
                    'config/*.js',
                    'translations/*.js',

                    // ensure modules loaded in correct order
                    'src/core/core.module.js',
                    'src/components/components.module.js',
                    'src/files/files.module.js',
                    'src/sending/sending.module.js',
                    'src/events/events.module.js',
                    'src/inboxes/inboxes.module.js',
                    'src/accounts_receivable/accounts_receivable.module.js',
                    'src/subscriptions/subscriptions.module.js',
                    'src/payment_setup/payment_setup.module.js',
                    'src/payment_plans/payment_plans.module.js',
                    'src/search/search.module.js',
                    'src/dashboard/dashboard.module.js',
                    'src/exports/exports.module.js',
                    'src/imports/imports.module.js',
                    'src/reports/reports.module.js',
                    'src/developer_tools/developer_tools.module.js',
                    'src/user_management/user_management.module.js',
                    'src/themes/themes.module.js',
                    'src/integrations/integrations.module.js',
                    'src/metadata/metadata.module.js',
                    'src/sign_up_pages/sign_up_pages.module.js',
                    'src/collections/collections.module.js',
                    'src/automations/automations.module.js',
                    'src/auth/auth.module.js',
                    'src/billing/billing.module.js',
                    'src/settings/settings.module.js',
                    'src/content/content.module.js',
                    'src/notifications/notifications.module.js',
                    'src/network/network.module.js',
                    'src/accounts_payable/accounts_payable.module.js',
                    'src/app.module.js',

                    // include remaining app code + templates
                    'src/**/!(*.spec).js',
                    'tmp/js/app.templates.js',
                ],
                dest: 'tmp/js/app.js',
            },
            vendor_js: {
                options: {
                    separator: ';',
                },
                src: [
                    // NPM JS dependencies go here as they are migrated from Bower
                    'node_modules/jquery/dist/jquery.js',
                    'vendor/angular.js',
                    'node_modules/v-button/dist/v-button.js',
                    'node_modules/chart.js/dist/Chart.js',
                    'node_modules/qrcode/build/qrcode.js',
                    'node_modules/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js',
                    'node_modules/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.js',
                    'node_modules/filepond/dist/filepond.min.js',
                    'tmp/js/vendor.bower.js',
                    'static/js/filepond.module.js',
                    'vendor/after/**/*.js',
                ],
                dest: 'tmp/js/vendor.js',
            },
            vendor_css: {
                src: [
                    // NPM CSS dependencies go here as they are migrated from Bower
                    'node_modules/v-button/dist/v-button.css',
                    'node_modules/@adyen/adyen-platform-experience-web/dist/adyen-platform-experience-web.css',
                    'node_modules/filepond/dist/filepond.css',
                    'tmp/css/vendor.bower.css',
                ],
                dest: 'tmp/css/vendor.css',
            },
        },
        'string-replace': {
            apiBaseUrl: {
                files: {
                    'tmp/js/app.js': 'tmp/js/app.js',
                },
                options: {
                    replacements: [
                        {
                            pattern: '$API_BASE_URL',
                            replacement: apiBaseUrl,
                        },
                    ],
                },
            },
        },
        browserify: {
            libs: {
                src: ['.'],
                dest: 'tmp/js/libs.js',
                options: {
                    alias: [
                        // modules we want to require and exports
                        '@adyen/adyen-platform-experience-web',
                    ],
                },
            },
        },

        /* Minification and file versioning tasks */

        uglify: {
            options: {
                mangle: false,
                ascii: true,
                sourceMap: true,
                ASCIIOnly: true,
            },
            app: {
                files: {
                    'tmp/js/app.js': 'tmp/js/app.js',
                },
            },
            libs: {
                files: {
                    'tmp/js/libs.js': 'tmp/js/libs.js',
                },
            },
            vendor: {
                files: {
                    'tmp/js/vendor.js': 'tmp/js/vendor.js',
                },
            },
        },
        cssmin: {
            app: {
                expand: true,
                cwd: 'tmp/css/',
                src: ['app.css'],
                dest: 'tmp/css',
            },
            vendor: {
                expand: true,
                cwd: 'tmp/css/',
                src: ['vendor.css'],
                dest: 'tmp/css',
            },
        },
        filerev: {
            options: {
                encoding: 'utf8',
                algorithm: 'md5',
                length: 8,
            },
            release: {
                files: [
                    {
                        src: 'tmp/css/*.css',
                        dest: 'tmp/css',
                    },
                    {
                        src: 'tmp/fonts/*.{woff,eot,ttf,svg}',
                        dest: 'tmp/fonts',
                    },
                    {
                        src: 'tmp/img/*.{svg,png,jpg,jpeg,gif}',
                        dest: 'tmp/img',
                    },
                    {
                        src: 'tmp/js/*.js',
                        dest: 'tmp/js',
                    },
                    {
                        src: 'tmp/*.{png,ico}',
                        dest: 'tmp',
                    },
                ],
            },
        },
        usemin: {
            html: ['tmp/index.html'],
            css: ['tmp/css/*.css'],
            options: {
                assetsDirs: ['tmp'],
            },
        },

        /* Local development server and change watching */

        connect: {
            server: {
                options: {
                    base: 'build',
                    hostname: 'app.invoiced.localhost',
                    port: 1236,
                    livereload: LIVE_RELOAD_PORT,
                    open: true,
                    middleware: function (connect, options) {
                        var middlewares = [];
                        var directory = options.directory || options.base[options.base.length - 1];

                        // set up the proxy
                        middlewares.push(require('grunt-connect-proxy/lib/utils').proxyRequest);

                        // enable Angular's HTML5 mode
                        middlewares.push(modRewrite(['^[^\\.]*$ /index.html [L]']));

                        // serve static files
                        if (!Array.isArray(options.base)) {
                            options.base = [options.base];
                        }
                        options.base.forEach(function (base) {
                            middlewares.push(serveStatic(base));
                        });

                        return middlewares;
                    },
                },
                proxies: [
                    {
                        context: '/api',
                        host: 'api.invoiced.localhost',
                        port: 1234,
                        https: false,
                        rewrite: {
                            '^/api': '/',
                        },
                        headers: {
                            host: 'api.invoiced.localhost',
                        },
                    },
                ],
            },
        },
        watch: {
            appIndex: {
                files: ['src/index.html'],
                tasks: ['copy:appIndex', 'copy:build', 'clean:temp'],
                options: {
                    livereload: LIVE_RELOAD_PORT,
                },
            },
            js: {
                files: ['src/**/*!(.spec).js', 'config.js', 'config/*.js', 'translations/*.js'],
                tasks: ['ngtemplates:app', 'concat:app_js', 'string-replace:apiBaseUrl', 'copy:build', 'clean:temp'],
                options: {
                    livereload: LIVE_RELOAD_PORT,
                },
            },
            css: {
                files: ['less/**/*.less'],
                tasks: ['less:app', 'copy:build'],
                options: {
                    livereload: LIVE_RELOAD_PORT,
                },
            },
            ngtemplates: {
                files: ['src/**/*.html'],
                tasks: ['ngtemplates:app', 'concat:app_js', 'string-replace:apiBaseUrl', 'copy:build', 'clean:temp'],
                options: {
                    livereload: LIVE_RELOAD_PORT,
                },
            },
            static: {
                files: ['static/**'],
                tasks: ['copy:staticFiles', 'copy:build', 'clean:temp'],
                options: {
                    livereload: LIVE_RELOAD_PORT,
                },
            },
        },
    });

    grunt.registerMultiTask('clean', 'Deletes files', function () {
        this.files.forEach(function (file) {
            file.orig.src.forEach(function (f) {
                if (grunt.file.exists(f)) {
                    grunt.file.delete(f);
                }
            });
        });
    });

    /* Setup Grunt Tasks */

    grunt.registerTask(
        'dev',
        'Builds the app quickly by skipping any minification/versioning of assets. Do not use in production.',
        [
            'bower_concat',
            'less',
            'ngtemplates',
            'concat',
            'string-replace:apiBaseUrl',
            'copy:vendor',
            'copy:staticFiles',
            'copy:appIndex',
            'browserify:libs',
            'clean:build',
            'copy:build',
            'clean:temp',
        ],
    );

    grunt.registerTask(
        'release',
        'Builds a production-ready version of the app. This includes CSS/JS minification and file versioning.',
        [
            'useminPrepare', // not in dev
            'bower_concat',
            'less',
            'ngtemplates',
            'concat',
            'string-replace:apiBaseUrl',
            'copy:vendor',
            'copy:staticFiles',
            'copy:appIndex',
            'uglify', // not in dev
            'cssmin', // not in dev
            'filerev', // not in dev
            'usemin', // not in dev
            'browserify:libs',
            'clean:build',
            'copy:build',
            'clean:temp',
        ],
    );

    grunt.registerTask('serve', 'Start a development web server and then listens for changes', function (target) {
        grunt.task.run(['configureProxies:server', 'connect:server', 'watch']);
    });

    grunt.registerTask('default', 'dev');
};
