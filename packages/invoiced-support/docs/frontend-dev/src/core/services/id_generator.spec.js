/* jshint -W117, -W030 */
describe('id generator', function () {
    'use strict';

    let IdGenerator;

    beforeEach(function () {
        module('app.core');

        inject(function (_IdGenerator_) {
            IdGenerator = _IdGenerator_;
        });
    });

    describe('generate', function () {
        it('should correctly generate an ID from a name', function () {
            expect(IdGenerator.generate('Invoiced Pro')).toEqual('invoiced-pro');

            expect(IdGenerator.generate('*$ % The $ * #-- - Name *)*#)')).toEqual('the-name');
        });
    });
});
