document.addEventListener("DOMContentLoaded", () => {
    const loginForm = document.getElementById("loginForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    
    function esContraseñaSegura(password) {
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$/;
        return regex.test(password);
    }

    // Validación en tiempo real al escribir en la contraseña
    passwordInput.addEventListener("input", () => {
        const valor = passwordInput.value;
        if (!esContraseñaSegura(valor)) {
            passwordInput.setCustomValidity("La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.");
            passwordInput.classList.add("is-invalid");
            passwordInput.classList.remove("is-valid");
        } else {
            passwordInput.setCustomValidity("");
            passwordInput.classList.remove("is-invalid");
            passwordInput.classList.add("is-valid");
        }
    });

    // Validación al enviar el formulario
    loginForm.addEventListener("submit", (event) => {
        if (!loginForm.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        loginForm.classList.add("was-validated");
    });
});