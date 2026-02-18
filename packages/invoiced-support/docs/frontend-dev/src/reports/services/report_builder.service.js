(function () {
    'use strict';

    angular.module('app.reports').factory('ReportBuilder', ReportBuilder);

    ReportBuilder.$inject = ['$translate', 'Metadata', 'CustomField', 'Core', 'selectedCompany'];

    function ReportBuilder($translate, Metadata, CustomField, Core, selectedCompany) {
        let config;
        let availableFields = [];
        let reportObjects = [];

        return {
            initialize: initialize,
            updateFieldMeta: updateFieldMeta,
            determineParameters: determineParameters,
            parameterName: parameterName,
            buildRequest: buildRequest,
            buildRequestSection: buildRequestSection,
            loadDefinition: loadDefinition,
            loadDefinitionSection: loadDefinitionSection,
        };

        function initialize(callback) {
            Metadata.reportFields(
                function (_config) {
                    config = angular.copy(_config.fields);
                    reportObjects = [];
                    angular.forEach(config, function (objectData) {
                        objectData.name = getObjectName(objectData.object);
                        reportObjects.push({
                            standalone: objectData.standalone,
                            name: objectData.name,
                            id: objectData.object,
                        });
                    });

                    loadCustomFields(function () {
                        availableFields = [];
                        createAvailableFields();
                        callback(reportObjects, availableFields);
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadCustomFields(callback) {
            CustomField.all(
                function (customFields) {
                    angular.forEach(customFields, function (customField) {
                        let field = {
                            id: 'metadata.' + customField.id,
                            name: customField.name,
                            type: customField.type,
                        };

                        if (customField.type === 'enum') {
                            field.values = {};
                            angular.forEach(customField.choices, function (value) {
                                field.values[value] = value;
                            });
                        }

                        if (customField.object === 'line_item') {
                            config.credit_note_line_item.fields.push(field);
                            config.estimate_line_item.fields.push(field);
                            config.invoice_line_item.fields.push(field);
                            config.pending_line_item.fields.push(field);
                            config.sale_line_item.fields.push(field);
                        } else if (customField.object === 'credit_note' || customField.object === 'invoice') {
                            config.sale.fields.push(field);
                        }

                        if (typeof config[customField.object] !== 'undefined') {
                            config[customField.object].fields.push(field);
                        }
                    });
                    callback();
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function createAvailableFields() {
            angular.forEach(reportObjects, function (reportObject) {
                let objectFields = [];
                let addedObjects = [];
                addObjectToAvailableFields(objectFields, reportObject.id, '', addedObjects);
                availableFields[reportObject.id] = objectFields;
            });
        }

        function addObjectToAvailableFields(availableFields, object, context, addedObjects) {
            // add fields for object type
            addedObjects.push(object);
            let objectData = config[object];
            let name = [];
            angular.forEach(context.split('.'), function (objectId) {
                if (typeof config[objectId] !== 'undefined') {
                    name.push(config[objectId].name);
                }
            });
            name = name.join(' ');
            if (!name) {
                name = objectData.name;
            }
            angular.forEach(objectData.fields, function (field) {
                if (field.type === 'metadata') {
                    return;
                }

                field = angular.copy(field);
                field.group = name;
                field.id = context + field.id;
                availableFields.push(field);
            });

            // add a field for count
            availableFields.push({
                group: name,
                id: context + 'count',
                name: objectData.name + ' Count',
                type: 'integer',
            });
        }

        function buildRequest(report) {
            report = angular.copy(report);
            let sections = [];
            angular.forEach(report.sections, function (section) {
                // Advanced editor mode
                if (section._advancedMode) {
                    sections.push(angular.fromJson(section._advancedInput));
                } else {
                    sections.push(buildRequestSection(section));
                }
            });
            report.sections = sections;

            return report;
        }

        function buildRequestSection(section) {
            section = angular.copy(section);

            delete section._advancedMode;
            delete section._advancedInput;

            if (section.type !== 'chart') {
                delete section.chart_type;
            }

            // Columns
            angular.forEach(section.fields, function (column) {
                buildRequestColumn(column);

                if (!column.name) {
                    delete column.name;
                }
            });

            // Filtering
            angular.forEach(section.filter, function (filterCondition) {
                buildRequestColumn(filterCondition);

                if (filterCondition.operator === 'true') {
                    filterCondition.operator = '=';
                    filterCondition.value = true;
                } else if (filterCondition.operator === 'false') {
                    filterCondition.operator = '=';
                    filterCondition.value = false;
                } else if (filterCondition.operator === 'empty') {
                    filterCondition.operator = '=';
                    filterCondition.value = null;
                } else if (filterCondition.operator === 'not_empty') {
                    filterCondition.operator = '<>';
                    filterCondition.value = null;
                } else if (filterCondition.operator === 'one_of' || filterCondition.operator === 'not_one_of') {
                    filterCondition.operator = filterCondition.operator === 'one_of' ? '=' : '<>';
                    if (angular.isArray(filterCondition.value)) {
                        let values = [];
                        angular.forEach(filterCondition.value, function (value) {
                            // strip empty values
                            if (!value) {
                                return;
                            }

                            // convert select2 values into native array
                            if (typeof value === 'object' && typeof value.id !== 'undefined') {
                                value = value.id;
                            }

                            values.push(value);
                        });
                        filterCondition.value = values;
                    }
                } else if (filterCondition.operator === 'between_date_range') {
                    filterCondition.operator = 'between';
                    filterCondition.value = '$dateRange';
                } else if (filterCondition.operator === 'not_between_date_range') {
                    filterCondition.operator = 'not_between';
                    filterCondition.value = '$dateRange';
                }
            });

            // Grouping
            angular.forEach(section.group, function (groupField) {
                buildRequestColumn(groupField);
                groupField.ascending = groupField.sort_direction === 'asc';
                delete groupField.sort_direction;
            });

            if (section.type === 'chart') {
                // Charts require a special grouping parameter
                section.group = [
                    {
                        field: section.fields[0].field,
                        name: section.fields[0].name || null,
                        expanded: false,
                    },
                ];
            }

            // Sorting
            angular.forEach(section.sort, function (sortField) {
                buildRequestColumn(sortField);
                sortField.ascending = sortField.sort_direction === 'asc';
                delete sortField.sort_direction;
            });

            return section;
        }

        function buildRequestColumn(column) {
            if (column._function) {
                if (column._function === 'count') {
                    column.field = {
                        function: 'count',
                    };
                } else {
                    column.field = {
                        function: column._function,
                        arguments: [
                            {
                                id: column._field,
                            },
                        ],
                    };
                }
            } else if (column._field) {
                column.field = {
                    id: column._field,
                };
            }

            delete column._field;
            delete column._function;
            delete column._meta;
        }

        function loadDefinition(definitionStr) {
            let definition = angular.fromJson(definitionStr);

            let report = {
                title: definition.title,
                sections: [],
            };

            angular.forEach(definition.sections, function (section) {
                report.sections.push(loadDefinitionSection(section));
            });

            return report;
        }

        function loadDefinitionSection(section) {
            let original = angular.copy(section);
            section._advancedMode = false;
            section._advancedInput = '';

            try {
                // Fields
                angular.forEach(section.fields, function (column) {
                    loadField(section, column);
                });

                // Filter
                angular.forEach(section.filter, function (filter) {
                    loadFilterCondition(section, filter);
                });

                // Group
                angular.forEach(section.group, function (group) {
                    loadField(section, group);
                    group.sort_direction = group.ascending ? 'asc' : 'desc';
                });

                // Sort
                angular.forEach(section.sort, function (sort) {
                    loadField(section, sort);
                    sort.sort_direction = sort.ascending ? 'asc' : 'desc';
                });

                return section;
            } catch (e) {
                if (e === 'advancedMode') {
                    return {
                        _advancedMode: true,
                        _advancedInput: JSON.stringify(original, null, 2),
                    };
                } else {
                    throw e;
                }
            }
        }

        function loadField(section, column) {
            column._field = '';
            column._function = '';
            if (typeof column.field !== 'undefined') {
                if (typeof column.field.function !== 'undefined') {
                    column._function = column.field.function;
                    if (column._function === 'count') {
                        column._field = 'count';
                    }

                    if (typeof column.field.arguments !== 'undefined') {
                        if (column.field.arguments.length === 1) {
                            if (typeof column.field.arguments[0].id !== 'undefined') {
                                column._field = column.field.arguments[0].id;
                            } else {
                                throw 'advancedMode';
                            }
                        } else if (column.field.arguments.length > 1) {
                            throw 'advancedMode';
                        }
                    }
                } else if (typeof column.field.expression !== 'undefined') {
                    // Expressions always require advanced mode
                    throw 'advancedMode';
                } else if (typeof column.field.id !== 'undefined') {
                    column._field = column.field.id;
                }
            }

            column._meta = {
                type: 'string',
                name: '',
            };
            updateFieldMeta(section.object, column);
        }

        function loadFilterCondition(section, filter) {
            if (!filter.operator) {
                filter.operator = '=';
            }

            if (filter.operator === 'and') {
                throw 'advancedMode';
            } else if (filter.operator === 'or') {
                throw 'advancedMode';
            }

            if (
                typeof filter.value === 'object' &&
                filter.value !== null &&
                !angular.isArray(filter.value) &&
                filter.field !== 'undefined'
            ) {
                throw 'advancedMode';
            }

            if (filter.operator === '=' && filter.value === true) {
                filter.operator = 'true';
            } else if (filter.operator === '=' && filter.value === false) {
                filter.operator = 'false';
            } else if (filter.operator === '=' && filter.value === null) {
                filter.operator = 'empty';
            } else if (filter.operator === '<>' && filter.value === null) {
                filter.operator = 'not_empty';
            } else if ((filter.operator === '=' || filter.operator === '<>') && angular.isArray(filter.value)) {
                filter.operator = filter.operator === '=' ? 'one_of' : 'not_one_of';
                // convert to select2 format
                let values = [];
                angular.forEach(filter.value, function (value) {
                    values.push({ id: value, text: value });
                });
                filter.value = values;
            } else if (filter.operator === 'between' && filter.value === '$dateRange') {
                filter.operator = 'between_date_range';
            } else if (filter.operator === 'not_between' && filter.value === '$dateRange') {
                filter.operator = 'not_between_date_range';
            }

            loadField(section, filter);
        }

        function updateFieldMeta(object, column) {
            let foundField = null;

            // Determine the parent object if this is a joined value
            let properties = column._field.split('.');
            let objectName = null;
            if (properties.length > 1 && properties[0] !== 'metadata') {
                // Recurse through the join chain until the targeted field is found
                for (let i = 0; i < properties.length; i++) {
                    let currentField = properties[i];

                    // When a metadata field is detected we must bail out here
                    // instead of continuing to explore the join chain.
                    if (currentField === 'metadata') {
                        foundField = getField(object, currentField + '.' + properties[i + 1]);
                        break;
                    }

                    let lookupField = getField(object, currentField);
                    if (lookupField) {
                        if (lookupField.join_object) {
                            object = lookupField.join_object;
                            objectName = lookupField.name;
                        }
                        foundField = lookupField;
                    }
                }
            } else {
                foundField = getField(object, column._field);
            }

            if (foundField) {
                column._meta.type = foundField.type;
                column._meta.name = (objectName || getObjectName(object)) + ' ' + foundField.name;
                column._meta.values = foundField.values || {};
                if (column._field === 'count' || column._field.substring(-6) === '.count') {
                    column._function = 'count';
                }
            }

            if (column._meta.type === 'enum') {
                column._meta.select2Options = {
                    width: '100%',
                    data: [],
                    multiple: true,
                    tokenSeparators: [','],
                };

                angular.forEach(column._meta.values, function (value, key) {
                    column._meta.select2Options.data.push({ id: key, text: value });
                });
            } else {
                column._meta.select2Options = {
                    width: '100%',
                    tags: [],
                    tokenSeparators: [','],
                };
            }

            column._meta.operators = [
                { id: '=', name: 'Equal to' },
                { id: '<>', name: 'Not equal to' },
                { id: 'one_of', name: 'One of' },
                { id: 'not_one_of', name: 'Not one of' },
                { id: 'contains', name: 'Contains' },
                { id: 'not_contains', name: 'Does not contain' },
                { id: 'empty', name: 'Empty' },
                { id: 'not_empty', name: 'Not empty' },
            ];

            if (column._meta.type === 'boolean') {
                column._meta.operators = [
                    { id: 'true', name: 'True' },
                    { id: 'false', name: 'False' },
                ];
            } else if (column._meta.type === 'date' || column._meta.type === 'datetime') {
                column._meta.operators = [
                    { id: '=', name: 'Equal to' },
                    { id: '<>', name: 'Not equal to' },
                    { id: '<', name: 'Before' },
                    { id: '<=', name: 'On or before' },
                    { id: '>', name: 'After' },
                    { id: '>=', name: 'On or after' },
                    { id: 'between_date_range', name: 'In date range' },
                    { id: 'not_between_date_range', name: 'Not in date range' },
                    { id: 'empty', name: 'Empty' },
                    { id: 'not_empty', name: 'Not empty' },
                ];
            } else if (
                column._meta.type === 'integer' ||
                column._meta.type === 'float' ||
                column._meta.type === 'money'
            ) {
                column._meta.operators = [
                    { id: '=', name: 'Equal to' },
                    { id: '<>', name: 'Not equal to' },
                    { id: '<', name: 'Less than' },
                    { id: '<=', name: 'Less than or equal to' },
                    { id: '>', name: 'Greater than' },
                    { id: '>=', name: 'Greater than or equal to' },
                ];
            }
        }

        function getObjectName(object) {
            return $translate.instant('metadata.object_names.' + object);
        }

        function getField(object, fieldId) {
            let foundField = null;
            angular.forEach(availableFields[object], function (field) {
                if (field.id === fieldId) {
                    foundField = field;
                    return false;
                }
            });

            return foundField;
        }

        function determineParameters(definition) {
            let parameters = {};
            angular.forEach(definition.sections, function (section) {
                addParametersFromFilter(section.filter, parameters);
            });

            return parameters;
        }

        function addParametersFromFilter(filter, parameters) {
            angular.forEach(filter, function (condition) {
                let name = condition.value;
                if (typeof name === 'string' && name.indexOf('$') === 0) {
                    if (name === '$now') {
                        // Always ignore $now because the backend fills this in
                        return;
                    } else if (name === '$currency') {
                        parameters[name] = selectedCompany.currency;
                    } else if (name === '$dateRange') {
                        parameters[name] = {
                            period: ['days', 30],
                        };
                    } else {
                        parameters[name] = '';
                    }
                } else if (angular.isArray(name)) {
                    addParametersFromFilter(name, parameters);
                }
            });
        }

        // Converts "$dateRange" -> "Date Range"
        function parameterName(id) {
            let name = id
                .substr(1) // remove $
                .replace(/([a-z])([A-Z])/g, '$1 $2'); // convert camel case to title case
            return name.charAt(0).toUpperCase() + name.slice(1); // capitalize first letter
        }
    }
})();
