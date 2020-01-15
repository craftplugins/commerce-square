/* global SqPaymentForm */

function initSquare() {
  if (typeof SqPaymentForm === 'undefined') {
    return setTimeout(initSquare, 100)
  }

  /**
   *
   * @param element HTMLElement
   * @param errors Array
   */
  function outputErrors(element, errors) {
    element.innerHTML = errors.map(function () {
      return '<li>' + outputErrors.message + '</li>'
    })
  }

  var containerElements = document.querySelectorAll('.square-payment-form-container')

  containerElements.forEach(function (containerElement) {
    var paymentForm
    var params = JSON.parse(containerElement.getAttribute('data-params'))
    var errorEl = containerElement.querySelector('.square-payment-form-errors')

    params.callbacks = {
      cardNonceResponseReceived: function (errors, nonce, cardData) {
        if (errors) {
          return outputErrors(errorEl, errors)
        }

        /** @type HTMLFormElement */
        var formElement = containerElement.closest('form')

        /** @type HTMLInputElement */
        var cardNonce = containerElement.querySelector('input[name="cardNonce"]')
        cardNonce.value = nonce

        /** @type HTMLInputElement */
        var verificationTokenEl = containerElement.querySelector('input[name="verificationToken"]')

        // @TODO: Move to params
        var verificationDetails = {
          intent: 'STORE',
          billingContact: {
            givenName: 'Jane',
            familyName: 'Doe',
          },
        }

        paymentForm.verifyBuyer(
          nonce,
          verificationDetails,
          function (errors, verificationResult) {
            if (errors) {
              return outputErrors(errorEl, errors)
            }

            verificationTokenEl.value = verificationResult.token

            formElement.submit()
          },
        )
      },
    }

    paymentForm = new SqPaymentForm(params)

    var formElement = containerElement.closest('form')
    formElement.addEventListener('submit', function (event) {
      event.preventDefault()

      paymentForm.requestCardNonce()
    })

    paymentForm.build()
  })
}

initSquare()
