(function () {
    'use strict';

    // This is a reference the user's currently selected company.
    // It should be globally available using Angular DI. You
    // should never overwrite this value, but always extend it
    // with angular.extend(selectedCompany, newValues).
    // Since objects are passed by ref in JS we have to extend it
    // so that any existing references have the latest version.
    // i.e. once the value is injected into the controller
    // it will not be re-injected but will maintain a reference
    // to the object.

    // WARNING if there is any possibility of modifying this
    // object (excluding the CurrentUser and Company services)
    // then you should clone it before using with angular.copy().

    angular.module('app.core').constant('selectedCompany', {});
})();
