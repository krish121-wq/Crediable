<?php
$content = file_get_contents('sales.php');

$replace1 = <<<JS
function applyTermContent() {
    const termId = $('#terms_title_select').val();
    if (!termId) {
        $('#terms_text').val('');
        return;
    }
    const term = termsList.find(t => t.id == termId);
    if (term) {
        $('#terms_text').val(term.content);
    }
}
JS;

$target1 = <<<JS
function applyTermContent() {
    const termId = $('#terms_title_select').val();
    if (!termId) {
        $('#terms_text').val('');
        return;
    }
    const term = termsList.find(t => t.id == termId);
    if (term) {
        $('#terms_text').val(term.content);
    }
}
JS;

$target2 = <<<JS
function prepareManageTermsModal() {
    $('#manage_terms_select').val('');
    $('#manage_term_id').val('');
    $('#manage_term_title').val('');
    $('#manage_term_content').val('');
    $('#btnDeleteTerm').hide();
}
JS;

$replace2 = <<<JS
function prepareManageTermsModal() {
    $('#manage_terms_select').val('');
    $('#manage_term_id').val('');
    $('#manage_term_title').val('');
    $('#manage_term_content').val('');
    $('#btnDeleteTerm').hide();
}

function openManageTermsModal() {
    prepareManageTermsModal();
    const modalEl = document.getElementById('manageTermsModal');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

// nested modal me typing allow karne ke liye (fix Bootstrap focus trap)
document.addEventListener('focusin', function(e) {
    if (e.target.closest('#manageTermsModal')) {
        e.stopImmediatePropagation();
    }
}, true);
JS;

// Only replace if openManageTermsModal is not there already
if (strpos($content, 'openManageTermsModal() {') === false) {
    $content = str_replace($target2, $replace2, $content);
}

file_put_contents('sales.php', $content);
echo "Replaced";
?>
