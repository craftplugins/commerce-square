/* global SqPaymentForm */

(function () {
  function displayErrors($errors, errors) {
    $errors.html(
      "<ul>" +
        $.map(errors, function (error) {
          return "<li>" + error + "</li>";
        }) +
        "</ul>"
    );
  }

  function getFormData($form) {
    return $form.serializeArray().reduce(function (object, item) {
      object[item.name] = item.value;
      return object;
    }, {});
  }

  $("[data-square]").each(function () {
    var $container = $(this);

    // Get the parameters from the data attribute
    var params = $container.data("square");

    console.log(params);

    // Generate an ID for the card element
    // var id = "sq-card-" + Math.random().toString(36).substring(7);

    // Create a card element and append it to the container
    // $("<div />").prop("id", id).appendTo($container);

    // Get the form and relevant fields
    var $form = $container.closest("form");
    var $nonce = $form.find('[name="nonce"]');
    var $verificationToken = $form.find('[name="verificationToken"]');

    // Create and append an errors container
    var $errors = $("<div />").addClass(params.errorClass).appendTo($container);

    // Create the Square Payment Form instance
    // https://developer.squareup.com/docs/payment-form/payment-form-walkthrough
    // noinspection JSUnusedGlobalSymbols
    var paymentForm = new SqPaymentForm(
      $.extend(params.paymentForm, {
        autoBuild: false,
        callbacks: {
          cardNonceResponseReceived: function (errors, nonce) {
            if (errors) {
              $form.data("processing", false);
              return displayErrors($errors, errors);
            }

            $nonce.val(nonce);

            if (params.verificationDetails) {
              // Verification (SCA)
              paymentForm.verifyBuyer(
                nonce,
                params.verificationDetails,
                function (errors, verificationResult) {
                  if (errors) {
                    $form.data("processing", false);
                    return displayErrors($errors, errors);
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
        },
        // card: {
        //   elementId: id
        // }
        cardNumber: {
          elementId: "sq-card-number",
          placeholder: "Card Number",
        },
        cvv: {
          elementId: "sq-cvv",
          placeholder: "CVV",
        },
        expirationDate: {
          elementId: "sq-expiration-date",
          placeholder: "MM/YY",
        },
        postalCode: {
          elementId: "sq-postal-code",
          placeholder: "Postal",
        },
      })
    );

    // Override the form submit event
    $form.on("submit", function (event) {
      event.preventDefault();

      if ($form.data("processing")) {
        return false;
      }
      $form.data("processing", true);

      // Request a nonce from Square
      paymentForm.requestCardNonce();
    });

    // Render the Square Payment Form
    paymentForm.build();
  });
})();
