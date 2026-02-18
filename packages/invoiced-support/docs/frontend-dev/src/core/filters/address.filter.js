(function () {
    'use strict';

    angular.module('app.core').filter('address', address);

    address.$inject = ['selectedCompany', 'Core'];

    function address(selectedCompany, Core) {
        return function (model, showName, showTaxId, showCountry) {
            let country = Core.getCountryFromCode(model.country);

            let taxIdName = 'Tax ID';
            if (typeof country.tax_id !== 'undefined') {
                taxIdName = country.tax_id[model.type];
            }

            if (typeof showName === 'undefined') {
                showName = true;
            }

            if (typeof showTaxId === 'undefined') {
                showTaxId = typeof country.hide_tax_id === 'undefined';
            }

            if (typeof showCountry === 'undefined') {
                showCountry = country.code !== selectedCompany.country;
            }

            let address = [];

            if (model.attention_to) {
                address.push('ATTN: ' + model.attention_to);
            }

            if (showName) {
                address.push(model.name);
            }

            if (model.address1) {
                address.push(model.address1);
            }

            if (model.address2) {
                address.push(model.address2);
            }

            if (model.city || model.state || model.postal_code) {
                let line3 = [];

                if (model.city) {
                    line3.push(model.city);
                }

                if (model.state) {
                    line3.push(model.state);
                }
                line3 = line3.join(', ');

                if (model.postal_code) {
                    line3 += ' ' + model.postal_code;
                }

                address.push(line3);
            }

            if (showCountry && country) {
                address.push(country.country);
            }

            if (showTaxId && model.tax_id) {
                address.push(taxIdName + ': ' + model.tax_id);
            }

            if (model.address_extra) {
                address.push(model.address_extra);
            }

            let cleanedUpAddress = address.filter(function (line) {
                return line !== null && line.trim() !== '';
            });

            return cleanedUpAddress.join('<br/>').replace('\n', '<br/>');
        };
    }
})();
