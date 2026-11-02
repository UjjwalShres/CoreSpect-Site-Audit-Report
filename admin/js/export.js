document.addEventListener('DOMContentLoaded', function () {

    const radios = document.querySelectorAll('input[name="cs_export_type"]');
    const downloadBtn = document.getElementById('cs-download');
    const previewBtn = document.getElementById('cs-preview');
    const nonceField = document.getElementById('cs-export-nonce');

    function getSelectedType() {
        let value = 'html';
        radios.forEach(r => {
            if (r.checked) value = r.value;
        });
        return value;
    }

    function updatePreviewVisibility() {
        const type = getSelectedType();

        if (type === 'html') {
            previewBtn.style.display = 'inline-block';
        } else {
            previewBtn.style.display = 'none';
        }
    }

    // Initial
    updatePreviewVisibility();

    // Change event
    radios.forEach(radio => {
        radio.addEventListener('change', updatePreviewVisibility);
    });

    // Download
    downloadBtn.addEventListener('click', function () {

        const type = getSelectedType();

        window.location.href =
            ajaxurl.replace('admin-ajax.php', 'admin-post.php') +
            '?action=cs_export&type=' + type +
            '&_wpnonce=' + nonceField.value;
    });

    // Preview
    previewBtn.addEventListener('click', function () {

        previewBtn.href =
            ajaxurl.replace('admin-ajax.php', 'admin-post.php') +
            '?action=cs_export&type=preview' +
            '&_wpnonce=' + nonceField.value;
    });

});
