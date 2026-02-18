/* jshint -W117, -W030 */

describe('currency-input directive', function () {
    'use strict';

    let elm, $scope;

    beforeEach(module('app.components'));

    beforeEach(inject(function ($rootScope, $compile) {
        $scope = $rootScope.$new();
        elm = angular.element('<input currency-input ng-model="model" currency="currency" />');
        $compile(elm)($scope);
    }));

    it('check US', function () {
        $scope.model = 10000000.24;
        $scope.currency = 'usd';
        $scope.$digest();
        expect(elm.val()).toEqual('$10,000,000.24');
    });
});
