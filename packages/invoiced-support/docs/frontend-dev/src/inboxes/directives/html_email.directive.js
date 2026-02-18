(function () {
    'use strict';

    angular.module('app.inboxes').directive('htmlEmail', htmlEmail);

    htmlEmail.$inject = ['$location', '$window'];

    function htmlEmail($location, $window) {
        if (typeof $window.resizeIframe === 'undefined') {
            $window.resizeIframe = function resizeIframe(obj) {
                //this is required for proper animation
                //css animation will be applied only after iframe completely loaded
                $(obj).closest('.email-body').addClass('has-message');
                obj.style.height = obj.contentWindow.document.documentElement.scrollHeight + 'px';
            };
        }

        return {
            restrict: 'E',
            // Sandbox iframe to minimize XSS attacks
            // Documentation: https://www.w3schools.com/tags/att_iframe_sandbox.asp
            // 'allow-same-origin' is required in order to be able to set the frame contents and resize the height dynamically.
            // 'allow-popups' is required to allow opening links in a new window.
            // 'allow-popups-to-escape-sandbox' allows linked pages to execute Javascript. See more: https://googlechrome.github.io/samples/allow-popups-to-escape-sandbox/
            template:
                '<iframe width="100%" frameborder="0" scrolling="no" onload="resizeIframe(this)" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>',
            scope: {
                content: '=',
            },
            link: function ($scope, element) {
                let theFrame = $('iframe', element)[0];
                $scope.$watch('content', function (html) {
                    let frameDocument = theFrame.contentWindow.document;
                    frameDocument.open();
                    frameDocument.write(html);
                    frameDocument.close();

                    let cssLink = frameDocument.createElement('link');
                    cssLink.href =
                        $location.protocol() + '://' + $location.host() + ':' + $location.port() + '/css/email.css';
                    cssLink.rel = 'stylesheet';
                    cssLink.type = 'text/css';
                    frameDocument.head.appendChild(cssLink);

                    let links = frameDocument.getElementsByTagName('a');
                    for (let i = 0; i < links.length; i++) {
                        links[i].setAttribute('target', '_blank');
                    }
                });
            },
        };
    }
})();
