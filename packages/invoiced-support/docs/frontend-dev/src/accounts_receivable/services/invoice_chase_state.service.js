/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('InvoiceChaseState', InvoiceChaseState);

    InvoiceChaseState.$inject = ['$filter'];

    function InvoiceChaseState($filter) {
        return {
            build: build,
            map: map,
            hasFailure: hasFailure,
        };

        /**
         * Formats invoice chasing state from API response.
         */
        function build(state) {
            let _state = [];
            angular.forEach(state, function (step) {
                _state.push(getStepState(step));
            });

            return _state;
        }

        /**
         * Creates a map of id -> step state from API response.
         */
        function map(state) {
            let _map = {};
            angular.forEach(state, function (step) {
                _map[step.id] = getStepState(step);
            });

            return _map;
        }

        function getStepState(step) {
            switch (step.trigger) {
                case 0:
                    return getOnIssueState(step);
                case 1:
                    return getBeforeDueState(step);
                case 2:
                    return getAfterDueState(step);
                case 3:
                    return getRepeaterState(step);
                case 4:
                    return getAbsoluteDateState(step);
                case 5:
                    return getAfterIssueState(step);
                default:
                    return {};
            }
        }

        function getOnIssueState(step) {
            let date = step.state[0].date;

            return {
                icon: 'fad fa-file-invoice-dollar',
                text: 'When Issued',
                channels: getChannels(step),
                skipped: step.state[0].skipped, // only has one send date
                sent: hasSent(step, step.state[0]),
                failures: step.state[0].failures,
                date: $filter('formatCompanyDateTime')(moment.utc(date).unix()),
            };
        }

        function getBeforeDueState(step) {
            let date = step.state[0].date;
            let text;
            if (step.options.days === 0) {
                text = 'On Due Date';
            } else {
                let daysText = step.options.days > 1 ? 'Days' : 'Day';
                text = step.options.days + ' ' + daysText + ' Before Due';
            }

            return {
                icon: 'fad fa-alarm-clock',
                text: text,
                channels: getChannels(step),
                skipped: step.state[0].skipped, // only has one send date
                sent: hasSent(step, step.state[0]),
                failures: step.state[0].failures,
                date: $filter('formatCompanyDateTime')(moment.utc(date).unix()),
            };
        }

        function getAfterDueState(step) {
            let date = step.state[0].date;
            let text;
            if (step.options.days === 0) {
                text = 'On Due Date';
            } else {
                let daysText = step.options.days > 1 ? 'Days' : 'Day';
                text = step.options.days + ' ' + daysText + ' After Due';
            }

            return {
                icon: 'fad fa-bells',
                text: text,
                channels: getChannels(step),
                skipped: step.state[0].skipped, // only has one send date
                sent: hasSent(step, step.state[0]),
                failures: step.state[0].failures,
                date: $filter('formatCompanyDateTime')(moment.utc(date).unix()),
            };
        }

        function getAbsoluteDateState(step) {
            let date = step.state[0].date;

            return {
                icon: 'fad fa-calendar-times',
                text: $filter('formatCompanyDate')(moment.utc(date).unix()),
                channels: getChannels(step),
                skipped: step.state[0].skipped, // only has one send date
                sent: hasSent(step, step.state[0]),
                failures: step.state[0].failures,
                date: $filter('formatCompanyDateTime')(moment.utc(date).unix()),
            };
        }

        function getRepeaterState(step) {
            // get number sends attempted
            let sent = 0;
            let states = step.state;
            let dateState = states[0];

            while (sent < states.length && (hasSent(step, dateState) || hasFailure(dateState) || dateState.skipped)) {
                sent++;
                dateState = states[sent];
            }

            // text
            let numDaysText = step.options.days > 1 ? step.options.days.toString() : '';
            let daysText = step.options.days > 1 ? 'Days' : 'Day';
            let text = 'Every ' + numDaysText + ' ' + daysText + ' (' + sent + '/' + states.length + ' sent)';

            // get last failure
            let current = Math.min(sent, states.length - 1);
            let failures = states[current].failures;
            while (!failures.email && !failures.sms && !failures.letter && current >= 0) {
                failures = states[current].failures;
                current--;
            }

            // mark as skipped if all sends have been skipped
            let skipped = states.reduce(function (_skipped, dateState) {
                return _skipped && dateState.skipped;
            }, true);

            // get next date
            let date = states[Math.min(sent, states.length - 1)].date;

            return {
                icon: 'fad fa-redo',
                text: text,
                channels: getChannels(step),
                skipped: skipped, // if all have been skipped
                sent: sent === states.length,
                failures: failures,
                date: $filter('formatCompanyDateTime')(moment.utc(date).unix()),
            };
        }

        function getAfterIssueState(step) {
            let date = step.state[0].date;
            let text;
            if (step.options.days === 0) {
                text = 'On Issue Date';
            } else {
                let daysText = step.options.days > 1 ? 'Days' : 'Day';
                text = step.options.days + ' ' + daysText + ' After Issue Date';
            }

            return {
                icon: 'fad fa-bells',
                text: text,
                channels: getChannels(step),
                skipped: step.state[0].skipped, // only has one send date
                sent: hasSent(step, step.state[0]),
                failures: step.state[0].failures,
                date: $filter('formatCompanyDateTime')(moment.utc(date).unix()),
            };
        }

        ///////////////////////////
        // Helpers
        ///////////////////////////

        function getChannels(step) {
            let channels = [];
            if (step.options.email) {
                channels.push({
                    key: 'email',
                    text: 'Email',
                });
            }

            if (step.options.sms) {
                channels.push({
                    key: 'sms',
                    text: 'Text',
                });
            }

            if (step.options.letter) {
                channels.push({
                    key: 'letter',
                    text: 'Letter',
                });
            }

            return channels;
        }

        function hasSent(step, dateState) {
            let channels = [];
            if (step.options.email) {
                channels.push('email');
            }
            if (step.options.sms) {
                channels.push('sms');
            }
            if (step.options.letter) {
                channels.push('letter');
            }

            return channels.reduce(function (value, channel) {
                if (!value) {
                    return false;
                }

                return value && dateState.sent[channel];
            }, true);
        }

        function hasFailure(stepState) {
            return stepState.failures.email || stepState.failures.sms || stepState.failures.letter;
        }
    }
})();
