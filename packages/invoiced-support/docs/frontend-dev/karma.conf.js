module.exports = function (config) {
    var configuration = {
        files: [
            // include configuration
            'config.js',
            'config/**/*.js',
            'translations/**/*.js',

            // include third party components
            'node_modules/jquery/dist/jquery.js',
            'components/jquery-ui/jquery-ui.js',
            'components/underscore/underscore.js',
            'vendor/angular.js',
            'components/angular-resource/angular-resource.js',
            'components/angular-cookies/angular-cookies.js',
            'components/angular-sanitize/angular-sanitize.js',
            'components/angular-translate/angular-translate.js',
            'components/angular-ui-router/release/angular-ui-router.js',
            'components/angular-ui-select2/src/select2.js',
            'components/angular-bootstrap/ui-bootstrap.js',
            'components/angular-bootstrap-colorpicker/js/bootstrap-colorpicker-module.js',
            'components/angular-ui-sortable/sortable.js',
            'components/angular-ui-codemirror/ui-codemirror.js',
            'components/angular-local-storage/dist/angular-local-storage.js',
            'components/angular-mocks/angular-mocks.js',
            'components/blueimp-file-upload/js/jquery.fileupload-angular.js',
            'components/codemirror/lib/codemirror.js',
            'components/codemirror/addon/mode/simple.js',
            'components/kennethkalmer-snapjs/snap.js',
            'components/jsrsasign/jsrsasign-latest-all-min.js',
            'components/moment/moment.js',
            'node_modules/v-button/dist/v-button.js',
            'components/vex/js/vex.js',
            'node_modules/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js',
            'node_modules/filepond-plugin-file-validate-size/dist/filepond-plugin-file-validate-size.js',
            'node_modules/filepond/dist/filepond.min.js',
            'static/js/filepond.module.js',
            'vendor/after/**/*.js',

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
            'src/collections/collections.module.js',
            'src/automations/automations.module.js',
            'src/sign_up_pages/sign_up_pages.module.js',
            'src/auth/auth.module.js',
            'src/billing/billing.module.js',
            'src/settings/settings.module.js',
            'src/content/content.module.js',
            'src/notifications/notifications.module.js',
            'src/network/network.module.js',
            'src/accounts_payable/accounts_payable.module.js',
            'src/app.module.js',

            // include remaining files
            'src/**/*.js',
        ],

        autoWatch: true,

        frameworks: ['jasmine'],

        browsers: ['ChromeHeadless', 'FirefoxHeadless'],

        customLaunchers: {
            FirefoxHeadless: {
                base: 'Firefox',
                flags: ['-headless'],
            },
        },

        browserNoActivityTimeout: 30000,

        concurrency: Infinity,

        plugins: ['karma-jasmine', 'karma-chrome-launcher', 'karma-firefox-launcher'],

        junitReporter: {
            outputFile: 'test_out/unit.xml',
            suite: 'unit',
        },
    };

    config.set(configuration);
};
