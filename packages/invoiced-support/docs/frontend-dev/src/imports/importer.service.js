(function () {
    'use strict';

    angular.module('app.imports').factory('Importer', Importer);

    Importer.$inject = [];

    function Importer() {
        return {
            findDuplicates: findDuplicates,
            matched: matched,
            parseColumns: parseColumns,
            parseFirstRow: parseFirstRow,
            removeSkippedColumns: removeSkippedColumns,
        };

        function matched(arr) {
            // check if each column is matched
            for (let i in arr) {
                if (!arr[i].id) {
                    return false;
                }
            }

            return true;
        }

        function parseFirstRow(row, importer) {
            let columns = [];

            angular.forEach(row, function parseFirstRowValue(value) {
                // do our best to match the column
                value = value.trim().replaceAll(' ', '_').replaceAll('/', '_').toLowerCase();

                // check if there's a match
                for (let i in importer.properties) {
                    let id = importer.properties[i].id;
                    if (value.toLowerCase() == id.toLowerCase()) {
                        columns.push({
                            id: id,
                        });
                        return;
                    } else if (typeof importer.properties[i].aliases !== 'undefined') {
                        let aliases = importer.properties[i].aliases;
                        for (let k in aliases) {
                            if (value == aliases[k]) {
                                columns.push({
                                    id: id,
                                });
                                return;
                            }
                        }
                    }
                }

                //check if this is a metadata column
                if (value.indexOf('metadata.') === 0) {
                    columns.push({
                        id: value,
                    });
                    return;
                }

                // no match, push an empty column
                columns.push({
                    id: '',
                });
            });

            // create entries for arbitrary metadata columns
            createMetadataColumns(columns, importer);

            return columns;
        }

        function parseColumns(columns) {
            let mapping = [];
            let skip = [];
            angular.forEach(columns, function (col, index) {
                if (col.id === 'skip') {
                    skip.push(index);
                } else {
                    mapping.push(col.id);
                }
            });

            return {
                mapping: mapping,
                skip: skip,
            };
        }

        // borrowed from http://stackoverflow.com/questions/840781/easiest-way-to-find-duplicate-values-in-a-javascript-array#840808
        function findDuplicates(arr) {
            let sorted_arr = arr.slice().sort(); // You can define the comparing function here.
            // JS by default uses a crappy string compare.
            // (we use slice to clone the array so the original array won't be modified)
            let results = [];
            for (let i = 0; i < arr.length - 1; i++) {
                if (sorted_arr[i + 1] == sorted_arr[i]) {
                    results.push(sorted_arr[i]);
                }
            }

            return results;
        }

        function removeSkippedColumns(records, skip) {
            records = angular.copy(records);
            skip = angular.copy(skip);

            skip.sort(sortNumber).reverse();

            for (let i in records) {
                for (let j in skip) {
                    if (typeof records[i][skip[j]] !== 'undefined') {
                        records[i].splice(skip[j], 1);
                    }
                }
            }

            return records;
        }

        function sortNumber(a, b) {
            return a - b;
        }

        function createMetadataColumns(columns, importer) {
            angular.forEach(columns, function (col) {
                if (col.id.indexOf('metadata.') === 0) {
                    // make sure this property does not already been set,
                    // i.e. a custom field
                    if (!propertyExists(col.id.toLowerCase(), importer)) {
                        importer.properties.push({
                            id: col.id,
                            name: col.id,
                        });
                    }
                }
            });
        }

        function propertyExists(id, columns) {
            let exists = false;
            angular.forEach(columns.properties, function (property) {
                if (property.id.toLowerCase() === id.toLowerCase()) {
                    exists = true;
                }
            });

            return exists;
        }
    }
})();
