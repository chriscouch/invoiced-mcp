/* globals moment */
(function () {
    'use strict';

    angular.module('app.components').factory('UiFilterService', UiFilterService);

    UiFilterService.$inject = [
        '$modal',
        'Core',
        'LeavePageWarning',
        'CurrentUser',
        'Customer',
        'Member',
        'Plan',
        'Money',
        'InvoicedConfig',
        'selectedCompany',
    ];

    function UiFilterService(
        $modal,
        Core,
        LeavePageWarning,
        CurrentUser,
        Customer,
        Member,
        Plan,
        Money,
        InvoicedConfig,
        selectedCompany,
    ) {
        let nameCache = {
            customer: {},
            plan: {},
            user: {},
        };

        return {
            saveFilter: function (filter, callback) {
                LeavePageWarning.block();
                const modalInstance = $modal.open({
                    templateUrl: 'ui/views/save-filter.html',
                    controller: 'SaveUiFilterController',
                    backdrop: 'static',
                    keyboard: false,
                    size: 'sm',
                    resolve: {
                        filter: function () {
                            return filter;
                        },
                    },
                });

                modalInstance.result.then(
                    function (result) {
                        LeavePageWarning.unblock();
                        callback(result);
                    },
                    function (error) {
                        LeavePageWarning.unblock();
                        if (error === 'cancel') {
                            return;
                        }
                        Core.flashMessage(error, 'error');
                    },
                );
            },
            sortFilters: function (filters) {
                if (!filters) {
                    return [];
                }
                return filters.sort(function (a, b) {
                    return a.name.localeCompare(b.name);
                });
            },
            buildFilterString: buildFilterString,
            hasValue: hasValue,
            serializeFilter: serializeFilter,
            getDefaultFilter: getDefaultFilter,
            setDefaultFieldValue: setDefaultFieldValue,
            clearFieldValue: clearFieldValue,
            getCurrencyChoices: getCurrencyChoices,
            getCountryChoices: getCountryChoices,
            buildCustomFieldFilters: buildCustomFieldFilters,
        };

        function hasValue(filter, field) {
            if (field.type === 'date' || field.type === 'datetime') {
                return typeof filter[field.id] === 'object' && (filter[field.id].start || filter[field.id].end);
            }

            if (field.type === 'boolean' || field.type === 'sort') {
                return !!filter[field.id];
            }

            return filter[field.id] && filter[field.id].operator;
        }

        function buildFilterString(filter, filterFields, cb) {
            let str = [];

            angular.forEach(filterFields, function (field) {
                let displayInFilterString = field.displayInFilterString;
                if (typeof displayInFilterString === 'function') {
                    displayInFilterString = displayInFilterString(filter);
                } else if (field.type === 'sort') {
                    displayInFilterString = false;
                }

                if (hasValue(filter, field) && displayInFilterString !== false) {
                    let displayValue = getDisplayValue(filter, field, function () {
                        if (typeof cb === 'function') {
                            cb(buildFilterString(filter, filterFields));
                        }
                    });
                    str.push(field.label + ': ' + displayValue);
                }
            });

            return str.sort().join(', ');
        }

        function getDisplayValue(filter, field, enrichCallback) {
            if (field.type === 'boolean') {
                let boolValue = filter[field.id];

                return boolValue === '1' ? 'True' : 'False';
            }

            if (field.type === 'enum') {
                let enumOperator = filter[field.id].operator;
                let enumValue = filter[field.id].value;
                let enumOperatorName = getOperatorName(enumOperator, field.type);

                if (['empty', 'not_empty'].includes(enumOperator)) {
                    return enumOperatorName;
                }

                for (let i in field.values) {
                    if (field.values[i].value === enumValue) {
                        enumValue = field.values[i].text;
                    }
                }

                return (enumOperatorName + ' ' + enumValue).trim();
            }

            if (['money', 'number', 'string'].includes(field.type)) {
                let operator = filter[field.id].operator;
                let stringValue = filter[field.id].value;
                let operatorName = getOperatorName(operator, field.type);

                if (['empty', 'not_empty'].includes(operator)) {
                    return operatorName;
                }

                let currency;
                if (field.type === 'money') {
                    currency =
                        filter.currency && filter.currency.value ? filter.currency.value : selectedCompany.currency;
                    stringValue = Money.currencyFormat(stringValue, currency, selectedCompany.moneyFormat);
                }

                if (operator === 'between') {
                    let rangeStart = filter[field.id].range_start;
                    let rangeEnd = filter[field.id].range_end;

                    if (field.type === 'money') {
                        rangeStart = Money.currencyFormat(rangeStart, currency, selectedCompany.moneyFormat);
                        rangeEnd = Money.currencyFormat(rangeEnd, currency, selectedCompany.moneyFormat);
                    }

                    return rangeStart + ' - ' + rangeEnd;
                }

                return (operatorName + ' ' + stringValue).trim();
            }

            if (['date', 'datetime'].includes(field.type)) {
                let startDate, endDate;
                let dateFormat = field.type === 'datetime' ? 'MMM D, YYYY, h:mm a' : 'MMM D, YYYY';

                // Start Date
                let startDateValue = filter[field.id].start;
                if (startDateValue instanceof Date || (typeof startDateValue === 'string' && startDateValue)) {
                    startDate = moment(startDateValue).format(dateFormat);
                } else if (!isNaN(startDateValue) && startDateValue > 0) {
                    startDate = moment.unix(startDateValue).format(dateFormat);
                }

                // End Date
                let endDateValue = filter[field.id].end;
                if (endDateValue instanceof Date || (typeof endDateValue === 'string' && endDateValue)) {
                    endDate = moment(endDateValue).format(dateFormat);
                } else if (!isNaN(endDateValue) && endDateValue > 0) {
                    endDate = moment.unix(endDateValue).format(dateFormat);
                }

                if (startDate && endDate) {
                    return startDate + ' - ' + endDate;
                } else if (startDate) {
                    return 'Since ' + startDate;
                } else if (endDate) {
                    return 'Until ' + endDate;
                }

                return '';
            }

            if (['customer', 'plan', 'user'].includes(field.type)) {
                let associationOperator = filter[field.id].operator;
                let idValue = filter[field.id].value;
                let associationOperatorName = getOperatorName(associationOperator, field.type);
                let associationName = getAssociationName(field.type, idValue, enrichCallback);

                if (['empty', 'not_empty'].includes(associationOperator)) {
                    return associationOperatorName;
                }

                return (associationOperatorName + ' ' + associationName).trim();
            }

            if (field.type === 'invoice_tags') {
                let tagsValue = filter[field.id];
                if (tagsValue instanceof Array) {
                    return tagsValue.join(' or ');
                }

                return tagsValue;
            }

            return filter[field.id];
        }

        function getOperatorName(operator, type) {
            if (operator === 'empty') {
                return 'Empty';
            } else if (operator === 'not_empty') {
                return 'Not Empty';
            } else if (operator === '=') {
                return '';
            } else if (operator === '<>') {
                if (type === 'string') {
                    return 'Does Not Match Exactly';
                }

                return 'Not Equal To';
            } else if (operator === 'contains') {
                return 'Contains';
            } else if (operator === 'not_contains') {
                return 'Does Not Contain';
            } else if (operator === 'starts_with') {
                return 'Starts With';
            } else if (operator === 'ends_with') {
                return 'Ends With';
            }

            return operator;
        }

        function getAssociationName(type, idValue, enrichCallback) {
            if (idValue && typeof idValue === 'object' && typeof idValue.id !== 'undefined') {
                idValue = idValue.id;
            }

            if (typeof nameCache[type][idValue] !== 'undefined') {
                return nameCache[type][idValue];
            }

            // Perform a callback with enriched information
            if (type === 'customer') {
                Customer.find({ id: idValue }, function (customer) {
                    nameCache[type][idValue] = customer.name;
                    enrichCallback();
                });
            } else if (type === 'plan') {
                Plan.find({ id: idValue }, function (plan) {
                    nameCache[type][idValue] = plan.name;
                    enrichCallback();
                });
            } else if (type === 'user') {
                Member.findAll(
                    {
                        'filter[user_id]': idValue,
                        paginate: 'none',
                    },
                    function (members) {
                        if (members.length === 1) {
                            nameCache[type][idValue] = members[0].user.first_name + ' ' + members[0].user.last_name;
                            enrichCallback();
                        }
                    },
                );
            }

            // Temporarily return the ID until the association name can be resolved
            return idValue;
        }

        // Serializes the standard filter parameters
        function serializeFilter(filter, filterFields) {
            let advancedFilter = [];
            angular.forEach(filterFields, function (field) {
                if (field.serialize === false || field.type === 'sort') {
                    return;
                }

                if (hasValue(filter, field)) {
                    let value = filter[field.id];
                    if (typeof value === 'object' && typeof value.operator !== 'undefined') {
                        if (['empty', 'not_empty'].includes(value.operator)) {
                            advancedFilter.push({
                                field: field.id,
                                operator: value.operator,
                            });
                        } else if (value.operator === 'between') {
                            advancedFilter.push({
                                field: field.id,
                                operator: '>=',
                                value: value.range_start,
                            });
                            advancedFilter.push({
                                field: field.id,
                                operator: '<=',
                                value: value.range_end,
                            });
                        } else {
                            let filterValue = value.value;
                            if (
                                filterValue &&
                                typeof filterValue === 'object' &&
                                typeof filterValue.id !== 'undefined'
                            ) {
                                filterValue = filterValue.id;
                            }

                            advancedFilter.push({
                                field: field.id,
                                operator: value.operator,
                                value: filterValue,
                            });
                        }
                    } else if (field.type === 'date') {
                        if (value.start) {
                            advancedFilter.push({
                                field: field.id,
                                operator: '>=',
                                value: value.start,
                            });
                        }
                        if (value.end) {
                            advancedFilter.push({
                                field: field.id,
                                operator: '<=',
                                value: value.end,
                            });
                        }
                    } else if (field.type === 'datetime') {
                        if (value.start) {
                            advancedFilter.push({
                                field: field.id,
                                operator: '>=',
                                value: moment(value.start).startOf('minute').toISOString(),
                            });
                        }
                        if (value.end) {
                            advancedFilter.push({
                                field: field.id,
                                operator: '<=',
                                value: moment(value.end).endOf('minute').toISOString(),
                            });
                        }
                    } else {
                        advancedFilter.push({
                            field: field.id,
                            operator: '=',
                            value: filter[field.id],
                        });
                    }
                }
            });

            return angular.toJson(advancedFilter);
        }

        function getDefaultFilter(filterFields, defaultPerPage) {
            let result = {
                page: 1,
                per_page: defaultPerPage,
            };

            angular.forEach(filterFields, function (field) {
                let defaultValue = typeof field.defaultValue !== 'undefined' ? field.defaultValue : '';
                if (field.type === 'date' || field.type === 'datetime') {
                    result[field.id] = { start: '', end: '' };
                } else if (field.type === 'boolean' || field.type === 'sort') {
                    result[field.id] = defaultValue;
                } else {
                    let defaultOperator = defaultValue ? '=' : '';
                    result[field.id] = {
                        operator: defaultOperator,
                        value: defaultValue,
                        range_start: '',
                        range_end: '',
                    };
                }
            });

            return result;
        }

        // Removes a field's value from the filter
        function clearFieldValue(filter, field) {
            if (field.type === 'date' || field.type === 'datetime') {
                filter[field.id] = { start: '', end: '' };
            } else if (field.type === 'boolean' || field.type === 'sort') {
                filter[field.id] = '';
            } else {
                filter[field.id] = { operator: '', value: '', range_start: '', range_end: '' };
            }
        }

        // Sets the field's value to the default on the filter
        function setDefaultFieldValue(filter, field) {
            let defaultValue = typeof field.defaultValue !== 'undefined' ? field.defaultValue : '';
            if (field.type === 'date' || field.type === 'datetime') {
                filter[field.id] = { start: '', end: '' };
            } else if (field.type === 'boolean' || field.type === 'sort') {
                filter[field.id] = defaultValue;
            } else {
                filter[field.id] = { operator: '=', value: defaultValue, range_start: '', range_end: '' };
            }
        }

        function getCurrencyChoices() {
            let result = [];
            angular.forEach(InvoicedConfig.currencies, function (currency, symbol) {
                if (selectedCompany.currencies.indexOf(symbol) !== -1) {
                    result.push({ value: symbol, text: symbol.toUpperCase() });
                }
            });

            return result;
        }

        function getCountryChoices() {
            let result = [];
            angular.forEach(InvoicedConfig.countries, function (country) {
                result.push({ value: country.code, text: country.country });
            });

            return result;
        }

        function buildCustomFieldFilters(customFields) {
            let fields = [];
            angular.forEach(customFields, function (customField) {
                let filter = {
                    id: 'metadata.' + customField.id,
                    label: customField.name,
                    type: customField.type,
                };

                if (filter.type === 'double') {
                    filter.type = 'string';
                }

                if (filter.type === 'enum') {
                    filter.values = [];
                    angular.forEach(customField.choices, function (value) {
                        filter.values.push({ text: value, value: value });
                    });
                }

                fields.push(filter);
            });

            return fields;
        }
    }
})();
