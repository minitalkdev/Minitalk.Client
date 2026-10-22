(function () {
    'use strict';
    var button = document.getElementById('MinitalkDatabaseUpdate');
    if (!button) return;
    var pending = false;
    button.addEventListener('click', function (event) {
        event.preventDefault();
        if (pending) return;
        pending = true;
        button.setAttribute('aria-disabled', 'true');
        button.textContent = '데이터베이스를 업데이트하고 있습니다. 잠시 기다려 주세요.';
        var data = new URLSearchParams();
        data.set('csrf_token', button.getAttribute('data-token'));
        data.set('schema_hash', button.getAttribute('data-schema'));
        fetch(button.getAttribute('data-url'), {method: 'POST', credentials: 'same-origin', body: data})
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success === true) { window.location.reload(); return; }
                button.textContent = (result.message || '업데이트를 완료하지 못했습니다.') + ' [다시 시도]';
            })
            .catch(function () { button.textContent = '응답을 확인하지 못했습니다. 다시 시도하면 현재 상태를 확인합니다. [다시 시도]'; })
            .finally(function () { pending = false; button.removeAttribute('aria-disabled'); });
    });
}());
