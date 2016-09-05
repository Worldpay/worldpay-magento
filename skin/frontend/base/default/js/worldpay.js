window.WorldpayIntegrationMode = 'template';
var wpjsForm;

function loadUpWP() {
    WorldpayMagentoVersion = '1.9.0';
    var cachedOnsubmit;
    var isPostForm = false;
    var selectedExisitingCard = false;
    var apmMode = '';
    var isOnePageCheckout = true;
    var originalSave;
    var inWorldpayMode = false;
    document.worldpayTemplateCallbackRec = false;
    var magentoCheckoutButton;
    var tokenCVCMode = false;
    if (!window.checkout) {
        isOnePageCheckout =  false;
    }

     window.WorldpayMagento = {
        threeDSError: function(error, url) {
            if (isOnePageCheckout) checkout.setLoadWaiting(false);
            if (!error) {
                error = '3DS Failed, please try again';
            }
            document.body.removeChild(document.getElementById('worldpay-threeDsFrame'));
            if (isOnePageCheckout) {
                checkout.gotoSection('payment', true);
            } else {
                if (url) {
                    window.location.href = url;
                }
            }
            alert(error);
        },
        loadThreeDS: function(url) {
            var iframeDiv =document.createElement('div');
            iframeDiv.id = 'worldpay-threeDsFrame';
            iframeDiv.style.display = 'none';
            iframeDiv.innerHTML = '<iframe width="100%" height="400px" src="'+ url +'"></iframe>';
            document.body.appendChild(iframeDiv);
        },
        loadCVC: function(token) {
            var self = this;  
            Worldpay.useTemplateForm({
                'form':wpjsForm,
                'token':token,
                'paymentSection': document.getElementById('wp_cvc_container'),
                'display':'inline',
                'type':'cvc',
                'saveButton': false,
                'dimensions': {
                    width: 220,
                    height: 220
                },
                'validationError': function() {

                },
                'beforeSubmit': function() {
                    return tokenCVCMode;             
                },
                'callback': function(obj) {
                    if (!tokenCVCMode) return;
                    if (obj && obj.cvc) {
                        document.getElementById('wp_token').value = token;
                        document.getElementById('worldpay-payment-errors').style.display = 'none';
                        if (isPostForm) {
                            wpjsForm.submit();
                        } else {
                            payment.save();
                        }
                     } else {
                        alert("Error, please try again");
                    }
                    return false;
                }
            });
        },
        checkoutThreeDS : function(e) {
            if (window.event) {
                window.event.cancelBubble = true;
                if (event.stopPropagation) {
                    event.stopPropagation();
                }
            }
            if (window.checkout) {
                 checkout.setLoadWaiting('review');
            } 
            if (!window.wp_threeDSCompleteURL) {
                window.wp_threeDSCompleteURL = '/index.php/worldpay/threeDS/checkout/';
            }

            window.WorldpayMagento.loadThreeDS(window.wp_threeDSCompleteURL);
            return false;
        },
        completeCheckoutThreeDS: function(url) {
            window.WorldpayMagento.threeDSComplete = true;
            document.location.href = url;
        },
        showThreeDSIframe: function() {
            document.getElementById('worldpay-threeDsFrame').style.display = '';
        },
        createWorldpayToken: function() {
            if (selectedExisitingCard) {
                Worldpay.submitTemplateForm();
            }
            else {
                var validated = Worldpay.card.createToken(wpjsForm, function(status, response) {
                    handleWorldpayErrors(status, response, function(message) {
                        document.getElementById('worldpay-payment-errors').style.display = 'none';
                        var token = message.token;
                        document.getElementById('wp_token').value = token;
                        if (isPostForm) {
                            wpjsForm.submit();
                        }
                        else {
                            payment.save();
                        }
                    });
                });
                if (validated === false) {
                    return false;
                }
                else {
                    return true;
                }
            }
        },
        createAPMToken: function() {
            Worldpay.reusable = false;
            if (apmMode == 'giropay') {
                if (!document.getElementById('wp-swift-code').value) {
                    alert('Please enter a swift code');
                    return false;
                }
                document.getElementById('wp-swift-code').setAttribute("data-worldpay-apm", "swiftCode");
            } else if (apmMode == 'ideal') {
                if (!document.getElementById('wp-bank-code').value) {
                    alert('Please enter a bank code');
                    return false;
                }
                document.getElementById('wp-bank-code').setAttribute("data-worldpay-apm", "shopperBankCode");
            } else {
                if (document.getElementById('wp-swift-code')) {
                    document.getElementById('wp-swift-code').removeAttribute("data-worldpay-apm", "swiftCode");
                }
                if (document.getElementById('wp-bank-code')) {
                    document.getElementById('wp-bank-code').removeAttribute("data-worldpay-apm", "shopperBankCode");
                }
            }
            if (document.getElementById('wp-apm-name')) {
                document.getElementById('wp-apm-name').value = apmMode;
            } else {
                var i = document.createElement("input");
                i.setAttribute('type',"hidden");
                i.setAttribute('id',"wp-apm-name");
                i.setAttribute('data-worldpay', 'apm-name');
                i.setAttribute('value', apmMode);
                wpjsForm.appendChild(i);
            }

            Worldpay.apm.createToken(wpjsForm, function(resp, message) {
                if (resp != 200) {
                    alert(message.error.message);
                    return;
                }
                var token = message.token;
                Worldpay.formBuilder(wpjsForm, 'input', 'hidden', 'payment[token]', token);
                if (isPostForm) {
                    wpjsForm.submit();
                }
                else {
                    payment.save();
                }
            });
            return false;
        },
        submitCard: function() {
            if (window.WorldpayIntegrationMode == 'ownForm') {
                window.WorldpayMagento.createWorldpayToken();
            } else {
                document.worldpayTemplateCallbackRec = false;
                Worldpay.submitTemplateForm();
            } 
        },
        handleWorldpayPayment: function() {
            var elements = $$('#payment-methods input');
            for (var i in elements) {
                if (elements[i].checked) {
                    if (elements[i].id.substr(0, 18) == 'p_method_worldpay_') {
                        var name = elements[i].id.substr(18);
                        if (name == 'cc') {
                            window.WorldpayMagento.submitCard();
                        } else {
                            apmMode = name;
                            selectedExisitingCard = false;
                            window.WorldpayMagento.createAPMToken();
                        }
                        return false;
                    }   
                    break;
                }
            }
            return true;
        },
        initialiseHooks: function() {
            if (window.WorldpayIntegrationMode != 'ownForm') {
                if (!Worldpay.clientKey) return;
                 var dummyform =document.createElement('form');
                 Worldpay.useTemplateForm({
                    'form': dummyform,
                    'paymentSection': 'worldpay-iframe',
                    'display':'inline',
                    'reusable': Worldpay.reusable,
                    'saveButton': false,
                    'callback': function(message) {
                        Worldpay.templateSaveButton = true;
                        if (tokenCVCMode) return;
                        if (!document.worldpayTemplateCallbackRec) {
                            document.worldpayTemplateCallbackRec = true;
                            var token = message.token;
                            document.getElementById('wp_token').value = token;
                            payment.save();
                        }
                    }
                });
            }
            //wpjsForm.submit = function() {};
            if ($$('#payment-buttons-container button')[0]) {
                magentoCheckoutButton = '#payment-buttons-container button';
            } else if ($$('#checkout-review-submit button')[0]) {
                magentoCheckoutButton = '#checkout-review-submit button';
            } else if ($$('#payment-continue')[0]) {
                 magentoCheckoutButton = '#payment-continue';
                  $$(magentoCheckoutButton)[0].setAttribute('onclick', 'return window.WorldpayMagento.handleWorldpayPayment()');
                 return;
            }
            if ($('payment_form_worldpay_cc')) {
                 Event.observe($('payment_form_worldpay_cc'), 'payment-method:switched-off', function(event){
                    selectedExisitingCard = true;
                    $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    inWorldpayMode = false;
                });

                Event.observe($('payment_form_worldpay_cc'), 'payment-method:switched', function(event){
                    selectedExisitingCard = false;
                    $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.submitCard()');
                    inWorldpayMode = true;
                });

                /// IF 3DS IS TURNED ON
                if (document.worldPayThreeDSEnabled && window.Review) {
                    originalSave = Review.prototype.save;
                    Review.prototype.save = function() {
                        if (window.WorldpayMagento.threeDSComplete || !inWorldpayMode) {
                            return originalSave.apply(this);
                        }
                        window.WorldpayMagento.checkoutThreeDS();
                    };
                }
                if ($('payment_form_worldpay_paypal')) {
                     Event.observe($('payment_form_worldpay_paypal'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_paypal'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'paypal';
                    });
                }
                if ($('payment_form_worldpay_giropay')) {
                     Event.observe($('payment_form_worldpay_giropay'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_giropay'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'giropay';
                    });
                }
                if ($('payment_form_worldpay_alipay')) {
                     Event.observe($('payment_form_worldpay_alipay'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_alipay'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'alipay';
                    });
                }
                if ($('payment_form_worldpay_ideal')) {
                     Event.observe($('payment_form_worldpay_ideal'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_ideal'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'ideal';
                    });
                }
                if ($('payment_form_worldpay_mistercash')) {
                     Event.observe($('payment_form_worldpay_mistercash'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_mistercash'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'mistercash';
                    });
                }
                if ($('payment_form_worldpay_przelewy24')) {
                     Event.observe($('payment_form_worldpay_przelewy24'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_przelewy24'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'przelewy24';
                    });
                }
                if ($('payment_form_worldpay_paysafecard')) {
                     Event.observe($('payment_form_worldpay_paysafecard'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_paysafecard'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'paysafecard';
                    });
                }
                if ($('payment_form_worldpay_postepay')) {
                     Event.observe($('payment_form_worldpay_postepay'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_postepay'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'postepay';
                    });
                }
                if ($('payment_form_worldpay_qiwi')) {
                     Event.observe($('payment_form_worldpay_qiwi'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_qiwi'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'qiwi';
                    });
                }
                if ($('payment_form_worldpay_sofort')) {
                     Event.observe($('payment_form_worldpay_sofort'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_sofort'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'sofort';
                    });
                }
                if ($('payment_form_worldpay_yandex')) {
                     Event.observe($('payment_form_worldpay_yandex'), 'payment-method:switched-off', function(event){
                        selectedExisitingCard = true;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'payment.save()');
                    });

                    Event.observe($('payment_form_worldpay_yandex'), 'payment-method:switched', function(event){
                        selectedExisitingCard = false;
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.createAPMToken()');
                        apmMode = 'yandex';
                    });
                }
            }

        }
    };

    wpjsForm = document.getElementById('co-payment-form') || document.getElementById('multishipping-billing-form');

    if (document.getElementById('review-button')) {
        isOnePageCheckout =  false;
        document.getElementById('review-button').setAttribute('onclick', 'return window.WorldpayMagento.checkoutThreeDS(event)');
    } else {
        if ( document.getElementById('multishipping-billing-form')) {
            Event.observe(window, 'load', function() {
                isPostForm = true;
                window.WorldpayMagento.initialiseHooks();
                
            });
        }
        else if (document.getElementById('co-payment-form')) {
            window.WorldpayMagento.initialiseHooks();
            isPostForm = false;
        }
        if (document.getElementById('worldpay-newcard')) {

            var newCardChange = function(){
                if (this.checked) {
                    tokenCVCMode = false;
                    document.getElementById('new-worldpay-card').style.display = 'block';
                    document.getElementById('worldpay_existing_cvc_box').style.display = 'none';
                    selectedExisitingCard = false;
                     if (document.getElementById('co-payment-form')) {
                        $$(magentoCheckoutButton)[0].setAttribute('onclick', 'window.WorldpayMagento.submitCard()');
                    }
                    else {
                        if (cachedOnsubmit) {
                            wpjsForm.onsubmit = cachedOnsubmit;
                        }
                    }
                }
            };

            if (document.getElementById('worldpay-newcard').addEventListener) {
                document.getElementById('worldpay-newcard').addEventListener("change", newCardChange, false);
            } else {
                document.getElementById('worldpay-newcard').attachEvent("change", newCardChange);
            }

            $$('.worldpay-savedcard-input').each(function(el) { 

                el.observe('click', function(event){
                    selectedExisitingCard = true;
                    document.getElementById('new-worldpay-card').style.display = 'none';
                    $(el).insert({
                        after:document.getElementById('worldpay_existing_cvc_box')
                    });
                    tokenCVCMode = true;
                    window.WorldpayMagento.loadCVC(el.getElementsByTagName('input')[0].value + "");
                    document.getElementById('worldpay_existing_cvc_box').style.display = 'block';
                    if (document.getElementById('co-payment-form')) {
                         $$(magentoCheckoutButton)[0].setAttribute('onclick', 'Worldpay.submitTemplateForm()');
                    }
                    else {
                        if (wpjsForm.onsubmit) {
                            cachedOnsubmit = wpjsForm.onsubmit;
                        }
                        wpjsForm.onsubmit = function(){return Worldpay.submitTemplateForm()};
                    }
                });
            });
        }

        function checkIfNewCard() {
            return !!getCheckedRadio(wpjsForm.elements.savedcard);
        }

        function getCheckedRadio(radio_group) {
            for (var i = 0; i < radio_group.length; i++) {
                var button = radio_group[i];
                if (button.checked) {
                    return button;
                }
            }
            return undefined;
        }

        function handleWorldpayErrors(status, response, success) {
            if (response.error) {
                document.getElementById('worldpay-payment-errors').style.display = 'block';
                Worldpay.handleError(wpjsForm, document.getElementById('worldpay-payment-errors'), response.error);
            } else if (status != 200) {
                document.getElementById('worldpay-payment-errors').style.display = 'block';
                if (!response.message) {
                    response.message = 'API error, please try again later';
                }
                Worldpay.handleError(wpjsForm, document.getElementById('worldpay-payment-errors'), response);
            } else {
                success(response);
            }
        }
    }
}

if (!window.Worldpay) {
    document.observe('dom:loaded', function(){
        loadUpWP();
    });
} else {
    loadUpWP();
}
