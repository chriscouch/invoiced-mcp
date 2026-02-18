/* jshint -W117, -W030 */
describe('importer service', function () {
    'use strict';

    let Importer;

    beforeEach(function () {
        module('app.imports');

        inject(function (_Importer_) {
            Importer = _Importer_;
        });

        jasmine.addMatchers({
            toEqualData: function () {
                return {
                    compare: function (actual, expected) {
                        let result = {};
                        result.pass = angular.equals(actual, expected);

                        if (result.pass) {
                            result.message =
                                'Expected this:\n' +
                                JSON.stringify(actual, null, 2) +
                                '\nto not match this:\n' +
                                JSON.stringify(expected, null, 2);
                        } else {
                            result.message =
                                'Expected this:\n' +
                                JSON.stringify(actual, null, 2) +
                                '\nto match this:\n' +
                                JSON.stringify(expected, null, 2);
                        }

                        return result;
                    },
                };
            },
        });
    });

    describe('findDuplicates', function () {
        it('should not find any duplicates', function () {
            let arr = [1, 2, 3];
            let dupes = Importer.findDuplicates(arr);

            expect(dupes).toEqual([]);
        });

        it('should find any duplicates', function () {
            let arr = [1, 2, 3, 2, 1];
            let dupes = Importer.findDuplicates(arr);

            expect(dupes).toEqual([1, 2]);
        });
    });

    describe('parseFirstRow', function () {
        it('should map the columns for a row', function () {
            let row = [
                'Some Property',
                'another_property',
                'nomatch',
                'nomatch',
                'metadata.test',
                'metadata.test2',
                'metadata.sales-rep',
                'phone_no',
            ];

            let importer = {
                properties: [
                    {
                        id: 'another_property',
                    },
                    {
                        id: 'some_property',
                    },
                    {
                        id: 'some_other_property',
                    },
                    {
                        id: 'metadata.sales-rep',
                    },
                    {
                        id: 'phone',
                        aliases: ['phone_number', 'phone_no'],
                    },
                ],
            };

            let columns = Importer.parseFirstRow(row, importer);

            let expected = [
                {
                    id: 'some_property',
                },
                {
                    id: 'another_property',
                },
                {
                    id: '',
                },
                {
                    id: '',
                },
                {
                    id: 'metadata.test',
                },
                {
                    id: 'metadata.test2',
                },
                {
                    id: 'metadata.sales-rep',
                },
                {
                    id: 'phone',
                },
            ];

            expect(columns).toEqualData(expected);

            expected = {
                properties: [
                    {
                        id: 'another_property',
                    },
                    {
                        id: 'some_property',
                    },
                    {
                        id: 'some_other_property',
                    },
                    {
                        id: 'metadata.sales-rep',
                    },
                    {
                        id: 'phone',
                        aliases: ['phone_number', 'phone_no'],
                    },
                    {
                        id: 'metadata.test',
                        name: 'metadata.test',
                    },
                    {
                        id: 'metadata.test2',
                        name: 'metadata.test2',
                    },
                ],
            };

            expect(importer).toEqualData(expected);
        });
    });

    describe('parseColumns', function () {
        it('should parse columns from selection', function () {
            let selection = [
                {
                    id: 'skip',
                },
                {
                    id: 'blah',
                },
                {
                    id: 'some_property',
                },
                {
                    id: 'skip',
                },
                {
                    id: 'skip',
                },
                {
                    id: 'skip',
                },
                {
                    id: 'another_property',
                },
                {
                    id: 'skip',
                },
            ];

            let parsed = Importer.parseColumns(selection);

            let expectedMapping = ['blah', 'some_property', 'another_property'];

            let expectedSkip = [0, 3, 4, 5, 7];

            expect(parsed.mapping).toEqual(expectedMapping);
            expect(parsed.skip).toEqual(expectedSkip);
        });
    });

    describe('matched', function () {
        it('should say all columns are matched', function () {
            let matched = Importer.matched([
                {
                    id: 'selected',
                },
                {
                    id: 'another',
                },
            ]);
            expect(matched).toEqual(true);
        });

        it('should say all columns are not matched', function () {
            let matched = Importer.matched([
                {
                    id: 'selected',
                },
                {
                    id: 'another',
                },
                {
                    id: '',
                },
            ]);
            expect(matched).toEqual(false);
        });
    });

    describe('removeSkippedColumns', function () {
        it('should correctly remove skipped columns', function () {
            let skip = [2, 3, 5, 7, 10, 11];
            let records = [
                [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
                [0, 1, 2, 3],
                [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
                [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            ];

            let trimmed = Importer.removeSkippedColumns(records, skip);

            let expected = [
                [0, 1, 4, 6, 8, 9, 12, 13, 14, 15],
                [0, 1],
                [0, 1, 4, 6, 8, 9, 12, 13, 14, 15],
                [0, 1, 4, 6, 8, 9],
                [0, 1, 4, 6, 8, 9, 12],
            ];

            expect(trimmed).toEqualData(expected);
        });
    });
});
