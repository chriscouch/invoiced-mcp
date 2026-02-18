(function () {
    'use strict';

    angular.module('app.search').factory('BrowsingHistory', BrowsingHistory);

    BrowsingHistory.$inject = ['localStorageService'];

    function BrowsingHistory(lss) {
        let maxItems = 5;

        return {
            push: addHistory,
            clear: clearHistory,
            history: getHistory,
        };

        function addHistory(entry) {
            let history = getHistory();

            // remove duplicates
            for (let i in history) {
                if (history[i].type == entry.type && history[i].id == entry.id) {
                    history.splice(i, 1);
                    break;
                }
            }

            history.unshift(entry);

            lss.set('history', history.slice(0, maxItems));
        }

        function getHistory() {
            return lss.get('history') || [];
        }

        function clearHistory() {
            lss.set('history', []);
        }
    }
})();
