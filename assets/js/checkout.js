var $ = jQuery;
$(document).ready(function () {

    if ($('#payment_method_cryptanil-payment-gateway').length) {

        var css = `@media only screen and (min-device-width: 1024px) {
                        #payment:hover {
                            position: relative;
                        }
                        #payment:hover .wc_payment_method.payment_method_cryptanil-payment-gateway label{
                           position: relative;
                        }
                        #payment:hover .wc_payment_method.payment_method_cryptanil-payment-gateway label:before {
                            content: url(${data.imgUrl});
                            position: absolute;
                            left: calc(100% + 20px);
                            z-index: 9;
                        }
                    }`,
            head = document.head || document.getElementsByTagName('head')[0],
            style = document.createElement('style');
        head.appendChild(style);
        style.type = 'text/css';
        if (style.styleSheet){
            style.styleSheet.cssText = css;
        } else {
            style.appendChild(document.createTextNode(css));
        }
    }
});
