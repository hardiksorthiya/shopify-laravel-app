(function () {
    const apiKeyMeta = document.querySelector('meta[name="shopify-api-key"]');
    const hostMeta = document.querySelector('meta[name="shopify-host"]');
    const apiKey = apiKeyMeta ? apiKeyMeta.content : '';
    const host = hostMeta ? hostMeta.content : '';

    if (window.shopify && apiKey && host) {
        window.shopify.createApp({
            apiKey: apiKey,
            host: host,
            forceRedirect: true
        });
    }

    const diamondBody = document.getElementById('diamond-body');
    const addRowButton = document.getElementById('add-row');

    if (!diamondBody || !addRowButton) {
        return;
    }

    function refreshRowIndexes() {
        const rows = diamondBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/diamonds\[\d+]/, 'diamonds[' + index + ']');
            });
        });
    }

    addRowButton.addEventListener('click', function () {
        const rowCount = diamondBody.querySelectorAll('tr').length;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="diamonds[${rowCount}][quality]"></td>
            <td><input type="text" name="diamonds[${rowCount}][color]"></td>
            <td><input type="number" step="0.01" name="diamonds[${rowCount}][min_ct]"></td>
            <td><input type="number" step="0.01" name="diamonds[${rowCount}][max_ct]"></td>
            <td><input type="number" step="0.01" name="diamonds[${rowCount}][price]"></td>
            <td><button type="button" class="btn btn-danger remove-row">Remove</button></td>
        `;
        diamondBody.appendChild(row);
    });

    diamondBody.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-row')) {
            const rows = diamondBody.querySelectorAll('tr');
            if (rows.length === 1) {
                return;
            }
            event.target.closest('tr').remove();
            refreshRowIndexes();
        }
    });
})();
