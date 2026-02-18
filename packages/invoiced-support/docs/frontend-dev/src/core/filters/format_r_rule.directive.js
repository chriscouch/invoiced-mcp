(function () {
    'use strict';

    angular.module('app.core').filter('formatRRule', formatRRule);

    formatRRule.$inject = ['AutomationBuilder'];

    function formatRRule(AutomationBuilder) {
        return function (trigger) {
            const text = AutomationBuilder.applyCompanyOffsetToTheRule(trigger.r_rule).toText();
            //edge case, when we set day of the week (no time is shown by default)
            const at = text.match(/at .*/);
            if (!at) {
                //build syntetic sting, which will be used to get the time
                const alternativeRRule = trigger.r_rule
                    .replace(/BY(DAY|MONTH|MONTHDAY)=[^;]+(;|$)/g, '')
                    .replace(/;$/, '')
                    .replace(/FREQ=[^;]+/, 'FREQ=DAILY');
                const alternativeText = AutomationBuilder.applyCompanyOffsetToTheRule(alternativeRRule).toText();
                const at = alternativeText.match(/at .*/);
                if (!at) {
                    return text;
                }
                const toReplace = enrichTimes(at[0]);
                return text + ' ' + toReplace;
            }

            const toReplace = enrichTimes(at[0]);
            return text.replace(at[0], toReplace);
        };
    }

    function enrichTimes(afterAt) {
        const hours = afterAt.match(/\d+/g);
        if (!hours) {
            return afterAt;
        }
        for (const i in hours) {
            let hour = hours[i];
            if (hour > 12) {
                hour -= 12;
                hour += 'PM';
            } else {
                hour += 'AM';
            }
            afterAt = afterAt.replace(hours[i], hour);
        }
        return afterAt;
    }
})();
