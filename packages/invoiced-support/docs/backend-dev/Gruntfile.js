module.exports = function (grunt) {
    require('load-grunt-tasks')(grunt);

    grunt.initConfig({
        less: {
            style: {
                files: {
                    'public/css/auth.css': 'assets/less/auth.less',
                    'public/css/network.css': 'assets/less/network.less',
                    'public/css/styles.css': 'assets/less/customer-portal/styles.less',
                    'public/css/integrations.css': 'assets/less/integrations.less',
                    'public/css/error.css': 'assets/less/error.less',
                },
            },
        },
        concat: {
            auth: {
                options: {
                    separator: ';',
                },
                src: ['assets/js/auth/**/*.js'],
                dest: 'public/js/auth.js',
            },
            customer_portal: {
                options: {
                    separator: ';',
                },
                src: ['assets/js/customer_portal/**/*.js'],
                dest: 'public/js/customer_portal.js',
            },
            jquery_ui_i18n: {
                options: {
                    separator: ';',
                },
                src: ['assets/js/jquery-ui-i18n/**/*.js'],
                dest: 'public/js/jquery-ui-i18n.js',
            },
        },
        copy: {
            views: {
                expand: true,
                cwd: 'assets/js/views/',
                src: ['**'],
                dest: 'public/js/views/',
            },
        },
        uglify: {
            options: {
                mangle: false,
                ascii: true,
            },
            js: {
                files: [
                    {
                        'public/js/auth.js': 'public/js/auth.js',
                        'public/js/customer_portal.js': 'public/js/customer_portal.js',
                        'public/js/jquery-ui-i18n.js': 'public/js/jquery-ui-i18n.js',
                    },
                    {
                        expand: true,
                        cwd: 'public/js/views',
                        src: '**/*.js',
                        dest: 'public/js/views',
                    },
                ],
            },
        },
        cssmin: {
            minify: {
                expand: true,
                cwd: 'public/css/',
                src: ['styles.css', 'auth.css', 'error.css', 'integrations.css', 'network.css'],
                dest: 'public/css',
            },
        },
        filerev: {
            options: {
                encoding: 'utf8',
                algorithm: 'md5',
                length: 8,
            },
            static: {
                src: [
                    'public/css/**/*.css',
                    'public/js/**/*.js',
                    'public/fonts/**/*',
                    'public/img/**/*.{jpg,jpeg,gif,png,webp,bmp,mp4,webm}',
                    'public/*.{ico,png}',
                ],
                dest: 'public/static',
            },
        },
        filerev_assets: {
            release: {
                options: {
                    dest: 'assets/static.assets.json',
                    cwd: 'public',
                },
            },
        },
        watch: {
            js: {
                files: ['assets/js/**/*.js'],
                tasks: ['concat', 'copy', 'uglify:js', 'filerev:static', 'filerev_assets'],
                options: {
                    livereload: true,
                },
            },
            css: {
                files: ['assets/less/**/*.less'],
                tasks: ['less:style', 'cssmin', 'filerev:static', 'filerev_assets'],
                options: {
                    livereload: true,
                },
            },
        },
    });

    // Create Tasks

    grunt.registerTask('release', ['less', 'concat', 'copy', 'uglify', 'cssmin', 'filerev', 'filerev_assets']);

    grunt.registerTask('default', 'release');
};
