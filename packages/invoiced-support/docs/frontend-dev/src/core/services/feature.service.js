(function () {
    'use strict';

    angular.module('app.core').factory('Feature', Feature);

    Feature.$inject = ['$resource', '$http', 'CurrentUser', 'InvoicedConfig', 'selectedCompany'];

    function Feature($resource, $http, CurrentUser, InvoicedConfig, selectedCompany) {
        let FeatureService = {
            hasFeature: hasFeature,
            hasAllFeatures: hasAllFeatures,
            hasSomeFeatures: hasSomeFeatures,
        };

        angular.extend(
            FeatureService,
            $resource(
                InvoicedConfig.apiBaseUrl + '/features/:id',
                {
                    id: '@id',
                },
                {
                    edit: {
                        method: 'PATCH',
                        transformResponse: $http.defaults.transformResponse.concat(function (response, header, status) {
                            if (status !== 200) {
                                return response;
                            }

                            let feature = response;

                            // remove from features list
                            let k = selectedCompany.features.indexOf(feature.id);
                            if (k !== -1) {
                                selectedCompany.features.splice(k, 1);
                            }

                            // add back if enabled
                            if (feature.enabled) {
                                selectedCompany.features.push(feature.id);
                            }

                            return feature;
                        }),
                    },
                },
            ),
        );

        return FeatureService;

        /**
         * Determines if user has given feature.
         *
         * @param {string|array} feature      The feature to be tested.
         * @returns {boolean}           True if the user has feature, False if not.
         */
        function hasFeature(feature) {
            if (angular.isArray(feature)) {
                return hasAllFeatures(feature);
            }

            return selectedCompany.features.indexOf(feature) !== -1;
        }

        /**
         * Determines if the user has ALL of the given features.
         *
         * @param {array} features      The array of features to be tested.
         * @returns {boolean}           True if the user has all features, False if not.
         */
        function hasAllFeatures(features) {
            let hasFeatures = true;
            angular.forEach(features, function (feature) {
                hasFeatures = hasFeatures && selectedCompany.features.indexOf(feature) !== -1;
            });
            return hasFeatures;
        }

        /**
         * Determines if the user has at least ONE of the given features.
         *
         * @param {array} features      The array of features to be tested.
         * @returns {boolean}           True if the user has one or more features, False if not.
         */
        function hasSomeFeatures(features) {
            let hasFeature = false;
            angular.forEach(features, function (feature) {
                if (selectedCompany.features.indexOf(feature) !== -1) {
                    hasFeature = true;
                }
            });
            return hasFeature;
        }
    }
})();
