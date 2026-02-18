/* globals moment */
(function () {
    'use strict';

    angular.module('app.automations').factory('AutomationBuilder', AutomationBuilder);

    AutomationBuilder.$inject = ['$window', 'Metadata', 'Core', 'CustomField', '$translate'];

    function AutomationBuilder($window, Metadata, Core, CustomField, $translate) {
        let config;

        return {
            getObjectTypes: getObjectTypes,
            getFieldsForAction: getFieldsForAction,
            fixOffset: fixOffset,
            applyCompanyOffsetToTheRule: applyCompanyOffsetToTheRule,
        };

        function load(callback) {
            Metadata.automationFields(
                function (_config) {
                    config = angular.copy(_config.fields);
                    loadCustomFields(callback);
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
                            choices: customField.choices,
                            writable: true,
                        };

                        if (customField.object === 'line_item') {
                            config.pending_line_item.properties.push(field);
                        }

                        if (typeof config[customField.object] !== 'undefined') {
                            config[customField.object].properties.push(field);
                        }
                    });

                    callback(config);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function getObjectTypes(callback) {
            load(function (result) {
                let objectTypes = [];
                angular.forEach(result, function (entry) {
                    entry.name = $translate.instant('metadata.object_names.' + entry.object);
                    objectTypes.push(entry);
                });

                callback(objectTypes);
            });
        }

        function getFieldsForAction(objectType, actionType, callback) {
            getObjectTypes(function (objectTypes) {
                const filteredObjectTypes = [];
                let allowedObjectTypes = [objectType];
                angular.forEach(objectTypes, function (entry) {
                    if (entry.object === objectType) {
                        allowedObjectTypes = allowedObjectTypes.concat(entry.associatedActionObjects);
                        return false;
                    }
                });

                const filteredAllowedObjectTypes = objectTypes.filter(
                    objectType => allowedObjectTypes.indexOf(objectType.object) !== -1,
                );

                angular.forEach(filteredAllowedObjectTypes, function (entry) {
                    if (entry.actions.indexOf(actionType) !== -1) {
                        normalizeProperties(entry);
                        filteredObjectTypes.push(entry);
                    }
                });

                //reduce object types to only those that support the action
                const filteredSubjectTypes = [];
                if (actionType === 'CreateObject') {
                    const core = objectTypes.find(entry => entry.object === objectType);
                    const coreAllowed = Object.keys(core.subjectActions[actionType]);
                    angular.forEach(objectTypes, function (entry) {
                        if (coreAllowed.indexOf(entry.object) !== -1) {
                            normalizeProperties(entry);
                            filteredSubjectTypes.push(entry);
                        }
                    });
                }

                callback(filteredObjectTypes, filteredSubjectTypes);
            });
        }

        function normalizeProperties(objectType) {
            const result = [];
            angular.forEach(objectType.properties, function (property) {
                //all join fields as behaved like writable strings
                if (property.type === 'join') {
                    property.writable = true;
                    property.type = 'string';
                    property.join = true;
                }
                // metadata property is not supported
                // company join not allowed
                if (property.type !== 'metadata' && property.id !== 'company') {
                    result.push(property);
                }
            });

            objectType.properties = result;
        }

        function fixOffset(item, offset) {
            let oft = parseInt(item) + offset;
            if (oft < 0) {
                oft = 24 + oft;
            } else if (oft > 23) {
                oft = oft - 24;
            }

            if (oft < 0 || oft > 23) {
                oft = 0;
            }
            return oft;
        }

        function applyCompanyOffsetToTheRule(rrule) {
            const offset = moment.parseZone().utcOffset() / 60;
            const rule = new $window.rrule.rrulestr(
                rrule.replace(/BYHOUR=([\d,]+)/, function (_, item2) {
                    return (
                        'BYHOUR=' +
                        item2
                            .split(',')
                            .map(item => fixOffset(item, offset))
                            .join(',')
                    );
                }),
            );
            if (rule.options.byhour) {
                rule.options.byhour = rule.options.byhour.map(item =>
                    fixOffset(item, moment.parseZone().utcOffset() / 60),
                );
            }

            return rule;
        }
    }
})();
