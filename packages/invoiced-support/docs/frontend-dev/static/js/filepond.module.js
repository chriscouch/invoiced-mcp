/* globals FilePondPluginFileValidateType, FilePondPluginFileValidateSize */
(function () {
    function r(e, n, t) {
        function o(i, f) {
            if (!n[i]) {
                if (!e[i]) {
                    var c = 'function' == typeof require && require;
                    if (!f && c) return c(i, !0);
                    if (u) return u(i, !0);
                    var a = new Error("Cannot find module '" + i + "'");
                    throw ((a.code = 'MODULE_NOT_FOUND'), a);
                }
                var p = (n[i] = { exports: {} });
                e[i][0].call(
                    p.exports,
                    function (r) {
                        var n = e[i][1][r];
                        return o(n || r);
                    },
                    p,
                    p.exports,
                    r,
                    e,
                    n,
                    t,
                );
            }
            return n[i].exports;
        }
        for (var u = 'function' == typeof require && require, i = 0; i < t.length; i++) o(t[i]);
        return o;
    }
    return r;
})()(
    {
        1: [
            function (require, module, exports) {
                (function (global) {
                    (function () {
                        'use strict';

                        function _typeof(obj) {
                            '@babel/helpers - typeof';
                            if (typeof Symbol === 'function' && typeof Symbol.iterator === 'symbol') {
                                _typeof = function _typeof(obj) {
                                    return typeof obj;
                                };
                            } else {
                                _typeof = function _typeof(obj) {
                                    return obj &&
                                        typeof Symbol === 'function' &&
                                        obj.constructor === Symbol &&
                                        obj !== Symbol.prototype
                                        ? 'symbol'
                                        : typeof obj;
                                };
                            }
                            return _typeof(obj);
                        }

                        //@ts-check
                        var angular =
                            typeof window !== 'undefined'
                                ? window['angular']
                                : typeof global !== 'undefined'
                                ? global['angular']
                                : null;

                        var filepond =
                            typeof window !== 'undefined'
                                ? window['FilePond']
                                : typeof global !== 'undefined'
                                ? global['FilePond']
                                : null;

                        angular.module('filepond').directive('filePond', genFilepondDirective);
                        var filteredComponentMethods = [
                            'setOptions',
                            'on',
                            'off',
                            'onOnce',
                            'appendTo',
                            'insertAfter',
                            'insertBefore',
                            'isAttachedTo',
                            'replaceElement',
                            'restoreElement',
                            'destroy',
                        ];

                        function createFilePondProxy(pond) {
                            var proxy = {};
                            Object.getOwnPropertyNames(pond).forEach(function (key) {
                                if (!pond.hasOwnProperty(key) || filteredComponentMethods.indexOf(key) !== -1) {
                                    return;
                                }

                                var prop = pond[key];

                                if (
                                    _typeof(prop) ===
                                    (typeof Function === 'undefined' ? 'undefined' : _typeof(Function))
                                ) {
                                    proxy[key] = function _() {
                                        prop(arguments);
                                    };
                                } else {
                                    Object.defineProperty(proxy, key, Object.getOwnPropertyDescriptor(pond, key));
                                }
                            });
                            return proxy;
                        }

                        function genFilepondDirective($log) {
                            return {
                                restrict: 'E',
                                scope: {
                                    config: '=',
                                    onInit: '=',
                                },
                                template: '<input type="file" class="filepond" />',
                                link: function link(scope, element) {
                                    if (!filepond.supported()) {
                                        $log.error("FilePondComponent: FilePond isn't supported by this browser");
                                        return;
                                    }

                                    filepond.registerPlugin(FilePondPluginFileValidateSize);
                                    filepond.registerPlugin(FilePondPluginFileValidateType);

                                    $log.debug('FilePondComponent: Initializing component...');
                                    var pond = filepond.create(element[0].children[0], scope.config);
                                    var removeWatcher = scope.$watch(
                                        function () {
                                            return scope.config;
                                        },
                                        function () {
                                            $log.debug('FilePondComponent: Refreshing configs...');

                                            try {
                                                pond.setOptions(scope.config);
                                            } catch (e) {
                                                $log.error(e);
                                            }
                                        },
                                        true,
                                    );

                                    pond.oninit = function () {
                                        $log.debug('FilePondComponent: Initialized');
                                        var proxy = createFilePondProxy(pond);

                                        try {
                                            scope.onInit({
                                                instance: proxy,
                                            });
                                        } catch (e) {
                                            $log.error(e);
                                        }
                                    };

                                    element.on('$destroy', function () {
                                        $log.debug('FilePondComponent: Detroying component...');
                                        filepond.destroy(element[0]);
                                        removeWatcher();
                                    });
                                },
                            };
                        }

                        genFilepondDirective.$inject = ['$log'];
                    }).call(this);
                }).call(
                    this,
                    typeof global !== 'undefined'
                        ? global
                        : typeof self !== 'undefined'
                        ? self
                        : typeof window !== 'undefined'
                        ? window
                        : {},
                );
            },
            {},
        ],
        2: [
            function (require, module, exports) {
                (function (global) {
                    (function () {
                        'use strict';

                        var angular =
                            typeof window !== 'undefined'
                                ? window['angular']
                                : typeof global !== 'undefined'
                                ? global['angular']
                                : null;

                        require('../utils/cssCreator')();

                        module.exports = angular.module('filepond', []).name;

                        require('../components/filepond.component');
                    }).call(this);
                }).call(
                    this,
                    typeof global !== 'undefined'
                        ? global
                        : typeof self !== 'undefined'
                        ? self
                        : typeof window !== 'undefined'
                        ? window
                        : {},
                );
            },
            { '../components/filepond.component': 1, '../utils/cssCreator': 3 },
        ],
        3: [
            function (require, module, exports) {
                'use strict';

                module.exports = function () {
                    if (!document) {
                        return;
                    }

                    var found = false;
                    document.head.childNodes.forEach(function (node) {
                        if (node instanceof HTMLStyleElement && node.id === 'filepond-style') {
                            found = true;
                        }
                    });

                    if (!found) {
                        var style = document.createElement('style');
                        style.id = 'filepond-style';
                        style.textContent = 'file-pond .filepond--root {' + 'width: 100%;' + 'height: 100%;' + '}';
                        document.head.appendChild(style);
                    }
                };
            },
            {},
        ],
    },
    {},
    [2],
);

//# sourceMappingURL=filepond.module.js.map
