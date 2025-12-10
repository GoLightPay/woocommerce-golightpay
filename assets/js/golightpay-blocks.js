/**
 * GoLightPay Blocks Payment Method Registration
 *
 * @package WooCommerce_GoLightPay
 */

(function () {
  "use strict";

  // Wait for WooCommerce Blocks to be available
  if (typeof window.wc === "undefined" || typeof window.wc.wcBlocksRegistry === "undefined") {
    console.warn("GoLightPay: WooCommerce Blocks registry not found");
    return;
  }

  const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

  if (!registerPaymentMethod) {
    console.warn("GoLightPay: registerPaymentMethod not found");
    return;
  }

  // Get payment method data from PHP
  const settings = window.wc_golightpay_blocks || {};

  console.log("GoLightPay: Settings", settings);

  // Get React and element utilities from WordPress (WooCommerce Blocks uses @wordpress/element)
  const { createElement, useEffect } = window.wp?.element || {};
  const { decodeEntities } = window.wp?.htmlEntities || {};

  if (!createElement) {
    console.error("GoLightPay: createElement not found. Available:", {
      wp: window.wp,
      React: window.React,
    });
    return;
  }

  if (!useEffect) {
    console.warn("GoLightPay: useEffect not found, hooks may not work");
  }

  const label = settings.title ? (decodeEntities ? decodeEntities(settings.title) : settings.title) : "GoLightPay";

  console.log("GoLightPay: Label", label);
  console.log("GoLightPay: isActive", settings.isActive);

  /**
   * Content component
   */
  const Content = createElement((props) => {
    const { eventRegistration, emitResponse } = props;

    const { onPaymentProcessing, onCheckoutAfterProcessingWithSuccess, onCheckoutAfterProcessingWithError } =
      eventRegistration;

    if (useEffect) {
      useEffect(() => {
        // Listen to payment processing (just for logging/monitoring)
        const unsubscribe1 = onPaymentProcessing(async () => {
          console.log("GoLightPay: Payment processing started");
        });

        // Handle successful checkout processing (optional - WooCommerce Blocks handles redirect automatically)
        const unsubscribe2 = onCheckoutAfterProcessingWithSuccess(async (result) => {
          console.log("GoLightPay: Checkout processing successful", result);
          // WooCommerce Blocks automatically handles redirect from process_payment response
        });

        // Handle checkout processing errors
        const unsubscribe3 = onCheckoutAfterProcessingWithError(async (result) => {
          console.error("GoLightPay: Checkout processing error", result);
        });

        // Unsubscribe when component is unmounted
        return () => {
          if (unsubscribe1) unsubscribe1();
          if (unsubscribe2) unsubscribe2();
          if (unsubscribe3) unsubscribe3();
        };
      }, [emitResponse?.responseTypes?.SUCCESS, emitResponse?.responseTypes?.ERROR, emitResponse?.responseTypes?.FAIL]);
    }

    // Display description if available
    if (settings.description) {
      return createElement("div", {
        className: "wc-block-components-payment-method-description",
        dangerouslySetInnerHTML: {
          __html: decodeEntities ? decodeEntities(settings.description) : settings.description,
        },
      });
    }

    return null;
  }, null);

  /**
   * Label component
   *
   * @param {*} props Props from payment API.
   */
  const Label = createElement((props) => {
    const { PaymentMethodLabel } = props.components || {};
    if (PaymentMethodLabel) {
      return createElement(PaymentMethodLabel, { text: label });
    }
    return createElement("span", null, label);
  }, null);

  /**
   * Check if payment method can be used
   */
  const canMakePayment = () => {
    const isActive = settings.isActive;
    return (
      isActive === true ||
      isActive === 1 ||
      (typeof isActive === "string" && ["true", "1", "yes"].includes(isActive.toLowerCase()))
    );
  };

  const golightpayPaymentMethod = {
    name: "golightpay",
    label: Label,
    content: Content,
    edit: Content,
    canMakePayment: canMakePayment,
    ariaLabel: label,
    supports: {
      features: settings?.supports || [],
    },
  };

  // Register payment method
  console.log("GoLightPay: Registering payment method", golightpayPaymentMethod);
  registerPaymentMethod(golightpayPaymentMethod);
  console.log("GoLightPay: Payment method registered");
})();
