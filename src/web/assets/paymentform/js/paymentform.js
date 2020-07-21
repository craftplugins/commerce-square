/* global SqPaymentForm */

(function ($) {
  $(".sq-form").each(function () {
    var $container = $(this);
    var $form = $container.closest("form");
    var $nonce = $('[name="nonce"]', $container);
    var $verificationToken = $('[name="verificationToken"]', $container);

    var params = $container.data("params");
    var isProcessing = false;

    function updateErrorMessage(elementId, errorMessage) {
      $("#" + elementId + " ~ .sq-error", $container).text(errorMessage);
    }

    function displayErrors(errors) {
      errors.forEach(function (error) {
        if (error.type === "VALIDATION_ERROR") {
          updateErrorMessage(
            params.paymentForm[error.field].elementId,
            error.message
          );
        }
      });
    }

    console.log(params);

    // Create the Square Payment Form instance
    // https://developer.squareup.com/docs/payment-form/payment-form-walkthrough
    // noinspection JSUnusedGlobalSymbols
    var paymentForm = new SqPaymentForm(
      $.extend(params.paymentForm, {
        callbacks: {
          paymentFormLoaded: function () {
            paymentForm.setPostalCode(params.initialPostalCode);
          },
          cardNonceResponseReceived: function (errors, nonce) {
            if (errors) {
              isProcessing = false;
              return displayErrors(errors);
            }

            $nonce.val(nonce);

            if (params.verificationDetails) {
              // Verification (SCA)
              paymentForm.verifyBuyer(
                nonce,
                params.verificationDetails,
                function (error, verificationResult) {
                  if (error) {
                    isProcessing = false;
                    return $(".sq-form-error", $container).text(error.message);
                  }

                  $verificationToken.val(verificationResult.token);
                  $form[0].submit();
                }
              );
            } else {
              // Just submit the form if we’re not verifying
              $form[0].submit();
            }
          },
          inputEventReceived: function (inputEvent) {
            if (inputEvent.eventType === "errorClassRemoved") {
              updateErrorMessage(inputEvent.elementId, "");
            }
          },
        },
      })
    );

    // Remove already bound events
    $form.off("submit");

    // Override the form submit event
    $form.on("submit", function (event) {
      event.preventDefault();
      if (!isProcessing) {
        isProcessing = true;
        paymentForm.requestCardNonce();
      }
    });

    // Render the Square Payment Form
    paymentForm.build();
  });
})(window.jQuery);
