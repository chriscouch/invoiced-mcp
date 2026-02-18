(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('ReceivePaymentForm', ReceivePaymentFormService);

    ReceivePaymentFormService.$inject = [
        '$filter',
        '$timeout',
        '$q',
        'Money',
        'selectedCompany',
        'Customer',
        'Invoice',
        'Estimate',
        'CreditNote',
        'Core',
        'PaymentCalculator',
    ];

    ReceivePaymentFormService.promises = {};

    function ReceivePaymentFormService(
        $filter,
        $timeout,
        $q,
        Money,
        selectedCompany,
        Customer,
        Invoice,
        Estimate,
        CreditNote,
        Core,
        PaymentCalculator,
    ) {
        let escapeHtml = $filter('escapeHtml');

        function ReceivePaymentForm(currency, amount, itemPreselected) {
            this.itemPreselected = itemPreselected || false;
            this._currency = currency;
            this.paymentAmount = amount;
            this._balance = null;
            this._invoices = [];
            this._estimates = [];
            this._creditNotes = [];
            this.error = null;
            this.appliedTo = [];
            this.openItems = [];
            this.availableCredits = [];
            this.preselectedCredits = [];
            this.itemToAdd = null;
            this.itemSelect2 = null;
            this.totalCredits = 0;
            this.totalApplied = 0;
            this.remaining = 0;
            this.invalidAmount = false;
            this.showOverAppliedAlert = false;
            this.showOverpaymentAlert = false;
            this.linesValid = true;
            this.creditsValid = true;
        }

        ReceivePaymentForm.prototype.preselectCredit = function (credit) {
            this.preselectedCredits.push(credit.id);
        };

        ReceivePaymentForm.prototype.loadData = function (customerId) {
            if (!ReceivePaymentFormService.promises[customerId]) {
                ReceivePaymentFormService.promises[customerId] = [
                    $q((resolve, reject) => this._loadBalance(customerId, resolve, reject)),
                    $q((resolve, reject) => this._loadInvoices(customerId, resolve, reject)),
                    $q((resolve, reject) => this._loadEstimates(customerId, 1, [], resolve, reject)),
                    $q((resolve, reject) => this._loadCreditNotes(customerId, 1, [], resolve, reject)),
                ];
            }

            let that = this;
            that.loading = true;
            $q.all(ReceivePaymentFormService.promises[customerId])
                .then(function resolveValues(values) {
                    that.loading = false;
                    that._balance = values[0];
                    that._invoices = values[1];
                    that._estimates = values[2];
                    that._creditNotes = values[3];

                    that._buildOpenItems();
                    that._calculateTotals();

                    // if there is only a single open item then select it automatically
                    if (that.openItems.length === 1 && that.appliedTo.length === 0) {
                        that.addDocument(that.openItems[0], false);
                    }
                })
                .finally(() => delete ReceivePaymentFormService.promises[customerId]);
        };

        ReceivePaymentForm.prototype._loadBalance = function (id, resolve, reject) {
            Customer.balance(
                {
                    id: id,
                },
                function (balance) {
                    resolve(balance);
                },
                function (result) {
                    Core.showMessage(result.data, 'error');
                    reject();
                },
            );
        };

        // Loads all unpaid invoices for this customer.
        ReceivePaymentForm.prototype._loadInvoices = function (customerId, resolve, reject) {
            let params = {
                'filter[customer]': customerId,
                'filter[paid]': false,
                'filter[closed]': false,
                'filter[draft]': false,
                'filter[voided]': false,
                sort: 'date ASC',
                include: 'expected_payment_date',
                page: 1,
            };

            let that = this;
            Invoice.all(
                params,
                function (invoices) {
                    resolve(that._hydrateInvoices(invoices));
                },
                function (result) {
                    Core.showMessage(result.data, 'error');
                    reject();
                },
            );
        };

        // Loads all open estimates for this customer.
        ReceivePaymentForm.prototype._loadEstimates = function (customerId, page, res, resolve, reject) {
            let params = {
                'filter[customer]': customerId,
                'filter[closed]': false,
                'filter[draft]': false,
                'filter[voided]': false,
                sort: 'date ASC',
                page: page,
            };

            let that = this;
            Estimate.findAll(
                params,
                function (estimates, headers) {
                    that._hydrateEstimates(estimates, res);
                    let links = Core.parseLinkHeader(headers('Link'));
                    if (links.next) {
                        // Load the next page
                        that._loadEstimates(customerId, page + 1, res, resolve, reject);
                    } else {
                        // We're finished loading all pages
                        resolve(res);
                    }
                },
                function (result) {
                    Core.showMessage(result.data, 'error');
                    reject();
                },
            );
        };

        // Loads all open credit notes for this customer.
        ReceivePaymentForm.prototype._loadCreditNotes = function (customerId, page, res, resolve, reject) {
            let params = {
                'filter[customer]': customerId,
                'filter[paid]': false,
                'filter[closed]': false,
                'filter[draft]': false,
                'filter[voided]': false,
                sort: 'date ASC',
                page: page,
            };

            let that = this;
            CreditNote.findAll(
                params,
                function (creditNotes, headers) {
                    that._hydrateCreditNotes(creditNotes, res);
                    let links = Core.parseLinkHeader(headers('Link'));
                    if (links.next) {
                        // Load the next page
                        that._loadCreditNotes(customerId, page + 1, res, resolve, reject);
                    } else {
                        // We're finished loading all pages
                        resolve(res);
                    }
                },
                function (result) {
                    Core.showMessage(result.data, 'error');
                    reject();
                },
            );
        };

        // Build the invoice list, excluding pending invoices
        ReceivePaymentForm.prototype._hydrateInvoices = function (invoices) {
            const result = [];
            angular.forEach(invoices, function (invoice) {
                if (invoice.status !== 'pending') {
                    // the text is the searchable part
                    invoice.text = [invoice.name, invoice.number].join(' ');
                    result.push(invoice);
                }
            });

            return result;
        };

        // Build the estimates list with only needing deposit payment
        ReceivePaymentForm.prototype._hydrateEstimates = function (estimates, result) {
            angular.forEach(estimates, function (estimate) {
                if (estimate.deposit > 0 && !estimate.deposit_paid) {
                    // the text is the searchable part
                    estimate.text = [estimate.name, estimate.number].join(' ');
                    result.push(estimate);
                }
            });

            return result;
        };

        // Build the credit note list
        ReceivePaymentForm.prototype._hydrateCreditNotes = function (creditNotes, res) {
            angular.forEach(creditNotes, function (creditNote) {
                // the text is the searchable part
                creditNote.text = [creditNote.name, creditNote.number].join(' ');
                res.push(creditNote);
            });

            return res;
        };

        ReceivePaymentForm.prototype.setCurrency = function (currency) {
            if (currency !== this._currency) {
                this._currency = currency;
                // reset payment application
                this.reset();
            }
        };

        ReceivePaymentForm.prototype.setPaymentAmount = function (paymentAmount) {
            this.paymentAmount = paymentAmount;
            this._calculateTotals();
        };

        ReceivePaymentForm.prototype.reset = function () {
            this.appliedTo = [];
            this._buildOpenItems();
            this._calculateTotals();
        };

        ReceivePaymentForm.clear = function () {
            ReceivePaymentForm.promises = {};
        };

        ReceivePaymentForm.prototype.changedAmount = function () {
            this._calculateTotals();
        };

        ReceivePaymentForm.prototype.applyAllCredits = function () {
            angular.forEach(this.availableCredits, function (creditLine) {
                creditLine.amount = creditLine.balance;
            });
            this._calculateTotals();
        };

        ReceivePaymentForm.prototype.canAutoApply = function () {
            return (
                (this.paymentAmount > 0 || this.totalCredits > 0) && !this.itemPreselected && this.openItems.length > 0
            );
        };

        ReceivePaymentForm.prototype.autoApply = function () {
            // Apply the payment amount plus selected credits in a waterfall
            // to the oldest open items until either the available amount is
            // used or there is no open balance left.
            this.appliedTo = [];
            let totalAvailable = Money.normalizeToZeroDecimal(this._currency, this.paymentAmount + this.totalCredits);

            let i = 0;
            while (totalAvailable > 0 && i < this.openItems.length) {
                let item = this.openItems[i++];
                if (item.currency !== this._currency) {
                    continue;
                }

                let balance = Money.normalizeToZeroDecimal(item.currency, item.balance || item.deposit);
                let lineAmount = Math.min(totalAvailable, balance);
                totalAvailable -= lineAmount;

                lineAmount = Money.denormalizeFromZeroDecimal(this._currency, lineAmount);
                this.addDocument(item, false, lineAmount, false);
            }

            this._buildItemSelect2();
            this.itemToAdd = null;
            this._calculateTotals();
        };

        ReceivePaymentForm.prototype.addAllOpenItems = function () {
            if (!this.itemSelect2) {
                return;
            }

            let that = this;
            angular.forEach(this.itemSelect2.data, function (item) {
                that.addDocument(item, false, null, false);
            });

            this._buildItemSelect2();
            this.itemToAdd = null;
            this._calculateTotals();
        };

        ReceivePaymentForm.prototype.addDocument = function (doc, preselected, amount, rebuild) {
            rebuild = typeof rebuild !== 'undefined' ? rebuild : true;
            if (!doc || doc.currency !== this._currency) {
                return;
            }

            let line = {
                type: doc.object,
                document: doc,
                amount: amount || 0,
                preselected: preselected,
            };

            // If an amount was not supplied then prefill the line amount based on the unapplied amount remaining
            if (line.amount === 0) {
                this._calculateTotals();
                line.amount = Math.max(0, Math.min(this.remaining, doc.balance || doc.deposit));
            }

            this.appliedTo.push(line);

            if (rebuild) {
                this._buildItemSelect2();
                this.itemToAdd = null;
                this._calculateTotals();
            }
        };

        ReceivePaymentForm.prototype.addOverpayment = function () {
            this.appliedTo.push({
                type: 'credit',
                amount: this.remaining,
            });
            this._buildItemSelect2();
            this._calculateTotals();
        };

        ReceivePaymentForm.prototype.canAddItem = function () {
            return this.itemSelect2 && this.itemSelect2.data.length > 0;
        };

        ReceivePaymentForm.prototype.canAddOverpayment = function () {
            for (let i in this.appliedTo) {
                if (this.appliedTo[i].type === 'credit') {
                    return false;
                }
            }

            return true;
        };

        ReceivePaymentForm.prototype.canDeleteAllLines = function () {
            for (let i in this.appliedTo) {
                if (this.appliedTo[i].preselected) {
                    return false;
                }
            }

            return true;
        };

        ReceivePaymentForm.prototype.canDeleteLine = function (line) {
            return !line.preselected;
        };

        ReceivePaymentForm.prototype.deleteLine = function (index) {
            this.appliedTo.splice(index, 1);
            this._buildItemSelect2();
            this._calculateTotals();
        };

        ReceivePaymentForm.prototype._calculateTotals = function () {
            let result = PaymentCalculator.calculateRemaining(
                this.paymentAmount,
                this.appliedTo,
                this.availableCredits,
                this._currency,
            );

            this.totalApplied = result[0];
            this.remaining = result[1];
            this.totalCredits = result[2];

            // validate payment amount and splits
            this.invalidAmount = !PaymentCalculator.validateAmount(this.paymentAmount, this.totalCredits);
            this.linesValid = PaymentCalculator.validateAppliedTo(this.appliedTo);
            this.creditsValid = PaymentCalculator.validateCredits(this.availableCredits);

            // show an alert if the amount applied is greater
            // than the payment amount
            this.showOverAppliedAlert = this.remaining < 0;

            // do not show overpayment alert unless there
            // is an unapplied amount remaining
            this.showOverpaymentAlert = this.remaining > 0;
        };

        ReceivePaymentForm.prototype._buildOpenItems = function () {
            // build open item list based on selected currency
            this.openItems = [];
            let that = this;
            angular.forEach(this._estimates, function (estimate) {
                if (estimate.currency === that._currency) {
                    that.openItems.push(estimate);
                }
            });
            angular.forEach(this._invoices, function (invoice) {
                if (invoice.currency === that._currency) {
                    that.openItems.push(invoice);
                }
            });

            this._buildItemSelect2();

            // build available credits based on selected currency
            this.availableCredits = [];
            if (this._balance && this._balance.available_credits > 0 && this._balance.currency === this._currency) {
                this.availableCredits.push({
                    type: 'applied_credit',
                    balance: this._balance.available_credits,
                    amount: 0,
                });
            }

            angular.forEach(this._creditNotes, function (creditNote) {
                if (creditNote.currency === that._currency) {
                    // handle a preselected credit note
                    let amount = 0;
                    if (that.preselectedCredits.indexOf(creditNote.id) !== -1) {
                        amount = creditNote.balance;
                    }

                    that.availableCredits.push({
                        type: 'credit_note',
                        document: creditNote,
                        balance: creditNote.balance,
                        amount: amount,
                    });
                }
            });
        };

        ReceivePaymentForm.prototype._buildItemSelect2 = function () {
            let excluded = this.appliedTo
                .map(function (item) {
                    return item.type + '-' + (item.document ? item.document.id : '');
                })
                .filter(function (item) {
                    return item;
                });

            let data = this.openItems.filter(function (item) {
                return excluded.indexOf(item.object + '-' + item.id) === -1;
            });

            // Unset select2 temporarily to cause a reset
            this.itemSelect2 = null;
            let that = this;
            $timeout(function () {
                that.itemSelect2 = that._getSelect2Config(data);
            });
        };

        ReceivePaymentForm.prototype._getSelect2Config = function (data) {
            return {
                placeholder: 'Select an item to add',
                width: '100%',
                data: data,
                formatSelection: formatSelectionSelect2,
                formatResult: formatResultSelect2,
            };
        };

        function formatSelectionSelect2(doc) {
            let amount = Money.currencyFormat(
                doc.balance || doc.deposit,
                doc.currency,
                selectedCompany.moneyFormat,
                true,
            );

            return escapeHtml(doc.number) + ' &mdash; ' + amount + ' balance';
        }

        function formatResultSelect2(doc) {
            let amount = Money.currencyFormat(
                doc.balance || doc.deposit,
                doc.currency,
                selectedCompany.moneyFormat,
                true,
            );

            return (
                "<div class='title'>" +
                escapeHtml(doc.name) +
                ' <small>' +
                escapeHtml(doc.number) +
                '</small>' +
                '</div>' +
                "<div class='details'>" +
                $filter('formatCompanyDate')(doc.date) +
                '<br/>' +
                amount +
                ' balance' +
                '</div>'
            );
        }

        ReceivePaymentForm.prototype.serializeAppliedTo = function () {
            let appliedTo = [];
            let appliedToInput = angular.copy(this.appliedTo);
            let availableCredits = angular.copy(this.availableCredits);

            // apply any credits first
            let that = this;
            angular.forEach(availableCredits, function (creditLine) {
                angular.forEach(appliedToInput, function (line) {
                    if (creditLine.amount <= 0) {
                        return;
                    }

                    if (line.type === 'invoice' || line.type === 'estimate') {
                        if (typeof line._newAmount === 'undefined') {
                            line._newAmount = line.amount;
                        }
                        if (line._newAmount <= 0) {
                            return;
                        }

                        let lineParams = {
                            type: creditLine.type,
                            document_type: line.type,
                        };
                        if (typeof creditLine.document !== 'undefined') {
                            lineParams[creditLine.document.object] = creditLine.document.id;
                        }
                        lineParams[line.type] = line.document.id;

                        // calculate credit to apply to this line item
                        lineParams.amount = Money.round(that.currency, Math.min(creditLine.amount, line._newAmount));

                        // reduce the remaining credit amount
                        creditLine.amount = Money.round(that.currency, creditLine.amount - lineParams.amount);

                        // reduce the line item payment amount
                        line._newAmount = Money.round(that.currency, line._newAmount - lineParams.amount);

                        appliedTo.push(lineParams);
                    }
                });
            });

            // Build remaining line items
            angular.forEach(appliedToInput, function (line) {
                let lineParams = {
                    type: line.type,
                    amount: line.amount,
                };

                if (typeof line.document !== 'undefined') {
                    lineParams[line.type] = line.document.id;
                }

                if (typeof line._newAmount !== 'undefined') {
                    lineParams.amount = line._newAmount;
                }

                if (lineParams.amount !== 0) {
                    appliedTo.push(lineParams);
                }
            });

            return appliedTo;
        };

        return ReceivePaymentForm;
    }
})();
