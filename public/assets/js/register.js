(function () {
    var $loginForm = document.forms['register'];

    $loginForm.addEventListener('submit', function (event) {
        var $csrf = Cookies.get('X-XSRF-TOKEN');

        // create the hidden input field
        var $hiddenCsrf = document.createElement('input');
        $hiddenCsrf.name = 'csrf_token';
        $hiddenCsrf.type = 'hidden';
        $hiddenCsrf.value = $csrf;

        $loginForm.appendChild($hiddenCsrf);

        // hash the password and its confirmation value
        // output is hex
        var hasher = new jsSHA('SHA-512', 'TEXT');
        var passwordInput = document.querySelector('input[name=password]');
        hasher.update(passwordInput.value);
        passwordInput.value = hasher.getHash('HEX');

        var passwordConfirmationInput = document.querySelector('input[name=password_confirmation]');
        hasher.update(passwordConfirmationInput.value);
        passwordConfirmationInput.value = hasher.getHash('HEX');

        // continue the form submission
        var eventTarget = event.target;
        setTimeout(function () {
            eventTarget.dispatch(event);
        }, 5000);

        // something wrong with the submission
        return false;
    });
})(this);