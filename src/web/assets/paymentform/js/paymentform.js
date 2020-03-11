/* global SqPaymentForm */

function initSquare() {
  if (typeof SqPaymentForm === 'undefined') {
    return setTimeout(initSquare, 100)
  }

  function displayErrors($errors, errors) {
    $errors.html(
      $.map(errors, function (error) {
        return '<li>' + error + '</li>'
      })
    )
  }

  function getFormData($form) {
    return $form.serializeArray().reduce(function (object, item) {
      object[item.name] = item.value
    }, {})
  }

  $('[data-square]').each(function () {
    var $container = $(this),
      params = $container.data('square'),
      $form = $container.closest('form'),
      $nonce = $form.find('[name="nonce"]'),
      $verificationToken = $form.find('[name="verificationToken"]'),
      $card = $('<div />').appendTo($container),
      $errors = $('<ul />').addClass(params.errorClass).appendTo($container)

    // noinspection JSUnusedGlobalSymbols
    var paymentForm = new SqPaymentForm(
      $.extend({
        card: $card[0],
        callbacks: {
          cardNonceResponseReceived: function (errors, nonce) {
            if (errors) {
              return displayErrors($errors, errors)
            }

            $nonce.val(nonce)
          }
        }
      }, params.square)
    )

    $form.on('submit', function (event) {
      event.preventDefault()

      var formData = getFormData($form)

      if (formData.paymentSourceId) {
        return $form.submit()
      }

      if ($form.data('processing')) {
        return false
      }

      $form.data('processing', true)

      paymentForm.requestCardNonce()

      paymentForm.verifyBuyer(
        nonce,
        params.verification,
        function (errors, verificationResult) {
          if (errors) {
            return displayErrors($errors, errors)
          }

          $verificationToken.val(verificationResult.token)

          $form.submit()
        }
      )
    })

    paymentForm.build()
  })

  /**
   * @param element HTMLElement
   * @param errors Array
   */
  function outputErrors(element, errors) {
    element.innerHTML = errors
      .map(function (error) {
        return '<li>' + error.message + '</li>'
      })
      .join('')
  }

  // var containerElements = document.querySelectorAll('.square-payment-form-container'), i
  //
  // for (i = 0; i < containerElements.length; i++) {
  //   var containerElement = containerElements[i]
  //   var paymentForm
  //   var params = JSON.parse(containerElement.getAttribute('data-params'))
  //
  //   /** @type HTMLFormElement */
  //   var formElement = containerElement.closest('form')
  //
  //   var errorsElement = containerElement.querySelector('.square-payment-form-errors')
  //
  //   if (!params.callbacks) {
  //     params.callbacks = {}
  //   }
  //
  //   params.callbacks.cardNonceResponseReceived = function (errors, nonce, cardData) {
  //     if (errors) {
  //       return outputErrors(errorsElement, errors)
  //     }
  //
  //     /** @type HTMLInputElement */
  //     var cardNonce = containerElement.querySelector('input[name="token"]')
  //     cardNonce.value = nonce
  //
  //     var verificationDetails = JSON.parse(containerElement.getAttribute('data-verificationDetails'))
  //
  //     paymentForm.verifyBuyer(
  //       nonce,
  //       verificationDetails,
  //       function (errors, verificationResult) {
  //         if (errors) {
  //           return outputErrors(errorsElement, errors)
  //         }
  //
  //         /** @type HTMLInputElement */
  //         var verificationTokenEl = containerElement.querySelector('input[name="verificationToken"]')
  //         verificationTokenEl.value = verificationResult.token
  //
  //         formElement.submit()
  //       }
  //     )
  //   }
  //
  //   paymentForm = new SqPaymentForm(params)
  //
  //   formElement.addEventListener('submit', function (event) {
  //     var formData = new FormData(formElement)
  //     if (!formData.get('paymentSourceId')) {
  //       event.preventDefault()
  //       paymentForm.requestCardNonce()
  //     }
  //   })
  //
  //   paymentForm.build()
  // }
}

initSquare()
