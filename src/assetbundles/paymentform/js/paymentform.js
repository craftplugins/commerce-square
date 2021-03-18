/* global SqPaymentForm */

(function ($) {
  $(".sq-form").each(function () {
    var $container = $(this);
    var $form = $container.closest("form");
    var $errors = $(".sq-form-error", $container).hide();
    var $nonce = $('[name="nonce"]', $container);
    var $verificationToken = $('[name="verificationToken"]', $container);

    var params = $container.data("params");
    var isProcessing = false;

    function displayErrors(errors) {
      var errorHtml = "";
      for (var i = 0; i < errors.length; i++) {
        errorHtml += "<li> " + errors[i].message + " </li>";
      }
      $errors.show().html(errorHtml);
    }

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
                    return displayErrors([error]);
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
              $errors.html("").hide();
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
