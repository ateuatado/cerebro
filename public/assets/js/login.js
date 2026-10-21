document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('pass-toggle');
    var password = document.getElementById('password');

    if (!toggle || !password) return;

    toggle.addEventListener('click', function () {
        var icon = toggle.querySelector('i');
        var visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        icon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
        toggle.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
    });
});
