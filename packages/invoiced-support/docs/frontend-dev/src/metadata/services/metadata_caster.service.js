/* globals moment */
(function () {
    'use strict';

    angular.module('app.metadata').factory('MetadataCaster', MetadataCaster);

    MetadataCaster.$inject = ['CustomField', 'selectedCompany', '$filter', 'Core', 'Money'];

    function MetadataCaster(CustomField, selectedCompany, $filter, Core, Money) {
        return {
            marshalForInvoiced: marshalForInvoiced,
            marshalForInput: marshalForInput,
            marshalForQuery: marshalForQuery,
            getDisplayValue: getDisplayValue,
        };

        // Casts a value for saving to Invoiced
        function marshalForInvoiced(objectType, values, cb) {
            if (typeof values !== 'object') {
                cb(false);
                return;
            }

            if (Object.keys(values).length === 0) {
                cb(values);
                return;
            }

            CustomField.all(function (customFields) {
                let fields = {};
                angular.forEach(customFields, function (customField) {
                    fields[customField.id] = customField;
                });

                let result = {};
                for (let key in values) {
                    let value = values[key];
                    if (typeof value !== 'undefined' && value !== null && value !== '') {
                        let customField = fields[key];
                        if (typeof customField !== 'undefined' && customField.object === objectType) {
                            value = castValue(customField.type, value);
                        }
                        result[key] = value;
                    }
                }

                cb(result);
            });
        }

        function castValue(type, value) {
            if (type === 'date' && typeof value === 'object' && value instanceof Date) {
                return moment(value).unix();
            } else if (type === 'money') {
                if (value !== null) {
                    return selectedCompany.currency + ',' + value;
                }
            }

            return value;
        }

        // Marshals a value for use in the custom field input
        function marshalForInput(customField, value) {
            if (typeof value === 'undefined' || value === null) {
                return null;
            }

            if (customField.type === 'date' && typeof value === 'number') {
                return moment.unix(value).toDate();
            } else if (customField.type === 'money') {
                let parts = (value + '').split(',');
                if (parts.length === 2) {
                    return parseFloat(parts[1]);
                } else {
                    return parseFloat(value);
                }
            }

            return value;
        }

        // Marshals a value for use in an API query
        function marshalForQuery(customField, value) {
            if (customField.type === 'boolean') {
                return value ? '1' : '0';
            }

            return value;
        }

        // Gets the text value of a metadata value
        function getDisplayValue(customField, value) {
            if (customField.type === 'string' || customField.type === 'enum') {
                if (typeof value === 'string') {
                    return $filter('linky')(value, '_blank');
                }
            } else if (customField.type === 'boolean') {
                if (value) {
                    return 'True';
                } else {
                    return 'False';
                }
            } else if (customField.type === 'date') {
                if (typeof value === 'number') {
                    return moment.unix(value).format(Core.phpDateFormatToMoment(selectedCompany.date_format));
                } else if (typeof value === 'object' && value instanceof Date) {
                    return moment(value).format(Core.phpDateFormatToMoment(selectedCompany.date_format));
                }
            } else if (customField.type === 'money') {
                let parts = (value + '').split(',');
                let currency, amount;

                if (parts.length === 2) {
                    currency = parts[0];
                    amount = parts[1];
                } else {
                    currency = selectedCompany.currency;
                    amount = value;
                }

                return Money.currencyFormat(amount, currency, selectedCompany.moneyFormat, true);
            }

            return value + '';
        }
    }
})();
