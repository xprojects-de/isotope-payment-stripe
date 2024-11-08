(function (window, document) {

    function isoStripeReady(callback) {
        if (document.readyState !== 'loading') {
            callback();
        } else if (document.addEventListener) {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            document.attachEvent('onreadystatechange', function () {
                if (document.readyState === 'complete') {
                    callback();
                }
            });
        }
    }

    function processIsoStripeResult(clientSession, bookingId, referenceId) {

        const stripeErrorContainerElement = document.getElementById('stripe-errorContainer');
        const stripeSuccessContainerElement = document.getElementById('stripe-successContainer');

        if (
            stripeErrorContainerElement !== undefined && stripeErrorContainerElement !== null &&
            stripeSuccessContainerElement !== undefined && stripeSuccessContainerElement !== null
        ) {

            stripeErrorContainerElement.style.display = 'none';
            stripeSuccessContainerElement.style.display = 'none';

        }

        document.dispatchEvent(
            new CustomEvent('isoStripeEvent', {
                detail: {
                    module: 'widgetPaymentStripe',
                    ab_type: 'event',
                    ab_clientSession: clientSession,
                    ab_booking: bookingId,
                    ab_reference: referenceId
                }
            })
        );

    }

    isoStripeReady(function () {

        document.addEventListener('isoStripeEvent', function (e) {

            if (e.detail) {

                const stripeContainerElement = document.getElementById('stripe-button-container');
                const stripeErrorContainerElement = document.getElementById('stripe-errorContainer');
                const stripeSuccessContainerElement = document.getElementById('stripe-successContainer');

                if (
                    stripeContainerElement !== undefined && stripeContainerElement !== null &&
                    stripeErrorContainerElement !== undefined && stripeErrorContainerElement !== null &&
                    stripeSuccessContainerElement !== undefined && stripeSuccessContainerElement !== null
                ) {

                    stripeErrorContainerElement.style.display = 'none';
                    stripeSuccessContainerElement.style.display = 'none';

                    const responseObject = JSON.parse(e.detail)
                    if (responseObject !== null && responseObject !== undefined) {

                        if (responseObject.error === false) {

                            stripeSuccessContainerElement.innerHTML = responseObject.message;
                            stripeSuccessContainerElement.style.display = 'block';

                        } else {

                            stripeErrorContainerElement.innerHTML = responseObject.message;
                            stripeErrorContainerElement.style.display = 'block';

                        }

                    }
                }

            }

        }, false);

        const stripeContainerElement = document.getElementById('stripe-button-container');
        if (stripeContainerElement !== undefined && stripeContainerElement !== null) {

            const clientSecret = stripeContainerElement.getAttribute('data-clientsecret');
            const publishableKey = stripeContainerElement.getAttribute('data-publishablekey');
            const bookingId = stripeContainerElement.getAttribute('data-bookingid');
            const clientSession = stripeContainerElement.getAttribute('data-clientsession');
            const referenceId = stripeContainerElement.getAttribute('data-referenceid');

            if (clientSecret !== undefined && clientSecret !== null) {

                initialize().then();

                async function initialize() {

                    const fetchClientSecret = async () => {
                        return clientSecret;
                    };

                    const handleComplete = async function (e) {

                        //checkout.destroy()
                        console.log(e);
                        // processIsoStripeResult(clientSession, bookingId, referenceId);

                    }

                    const checkout = await Stripe(publishableKey).initEmbeddedCheckout({
                        fetchClientSecret,
                        onComplete: handleComplete
                    });

                    checkout.mount(stripeContainerElement);

                }

            }

        }

    }, false);
})(window, document);


