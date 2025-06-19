function togglePassword(id, toggleButton) {
    const pw = document.getElementById(id);
    if (pw.type === "password") {
        pw.type = "text";
        toggleButton.textContent = "Hide";
    } else {
        pw.type = "password";
        toggleButton.textContent = "Show";
    }
}
