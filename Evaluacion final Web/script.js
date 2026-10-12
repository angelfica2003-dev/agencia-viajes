document.addEventListener("DOMContentLoaded", () => {
    const passwordInput = document.getElementById("contrasena")
        || document.getElementById("password");
    const form = passwordInput ? passwordInput.closest("form") : null;

    if (!passwordInput || !form) {
        return;
    }

    const esContrasenaSegura = (password) =>
        /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(password);

    passwordInput.addEventListener("input", () => {
        if (!esContrasenaSegura(passwordInput.value)) {
            passwordInput.setCustomValidity(
                "Usa al menos 8 caracteres, una mayúscula, una minúscula y un número."
            );
            passwordInput.classList.add("is-invalid");
            passwordInput.classList.remove("is-valid");
        } else {
            passwordInput.setCustomValidity("");
            passwordInput.classList.remove("is-invalid");
            passwordInput.classList.add("is-valid");
        }
    });

    form.addEventListener("submit", (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.reportValidity();
        }
        form.classList.add("was-validated");
    });
});
