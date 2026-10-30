
const salesData = {};
const productsData = {};

let currentExportType = '', selectedDuration = '', customRange = null, productCount = 0;
let payOutstandingVal = 0;

function buildProductOptions(selectedId) {
    let html = '<option value="">— Select Product —</option>';
    productsData.forEach(p => {
        const sel = (selectedId && p.id == selectedId) ? 'selected' : '';
        let taxLabel = `GST ${p.tax}%`;
        html += `<option value="${p.id}" data-price="${p.price}" data-excl="${p.excl}" data-tax="${p.tax}" data-sgst="${p.sgst}" data-cgst="${p.cgst}" data-igst="${p.igst}" data-stock="${p.stock}" ${sel}>
            ${p.name} | ₹${p.price.toFixed(2)} incl. | ${taxLabel} | Stk:${p.stock}
        </option>`;
    });
    return html;
}

function makeProductRow(idx, item) {
    const qty = item ? item.quantity : '';
    const disc = item ? (item.discount_percent || 0) : 0;
    const pid = item ? item.product_id : '';
    const ttype = item ? (item.transaction_type || 'sale') : 'sale';
    return `
    <div class="product-row" id="prow_${idx}" style="grid-template-columns: 2fr 1.1fr 0.8fr 0.8fr 1.5fr 36px; gap:8px;">
        <div class="mfield" style="margin-bottom:0">
            <label>Product <span style="color:var(--danger)">*</span></label>
            <select class="product-select" data-idx="${idx}" required>${buildProductOptions(pid)}</select>
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Type</label>
            <select class="type-select" name="transaction_type[]" data-idx="${idx}" style="font-weight:700">
                <option value="sale" ${ttype==='sale'?'selected':''}>🏷️ Sale</option>
                <option value="rent" ${ttype==='rent'?'selected':''}>🔄 Rent</option>
            </select>
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Qty <span style="color:var(--danger)">*</span></label>
            <input type="number" class="qty-input" data-idx="${idx}" min="1" value="${qty}" placeholder="0" required>
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Disc %</label>
            <input type="number" class="disc-input" data-idx="${idx}" min="0" max="100" step="0.01" value="${disc}" placeholder="0">
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Serials (Comma sep)</label>
            <input type="text" class="serials-input" data-idx="${idx}" value="${item?item.serial_numbers||'':''}" placeholder="e.g. SN1,SN2">
        </div>
        <div style="display:flex;align-items:flex-end;padding-bottom:0">
            <button type="button" class="remove-product-btn" onclick="removeRow(${idx})"><i class="fas fa-trash"></i></button>
        </div>
    </div>

    <div class="price-preview" id="preview_${idx}">
        <div class="pp-row"><span>Rate (excl. GST)</span><span id="pp_excl_${idx}">—</span></div>
        <div class="pp-row"><span>Taxable Amt</span><span id="pp_taxable_${idx}">—</span></div>
        <div class="pp-tax-detail" id="pp_taxdetail_${idx}"></div>
        <div id="pp_disc_row_${idx}" style="display:none" class="pp-row" style="color:var(--success)"><span>Discount</span><span id="pp_disc_${idx}">—</span></div>
        <div class="pp-row total"><span>Line Total (incl. GST)</span><span id="pp_total_${idx}">—</span></div>
    </div>`;
}

function addRow(item) {
    const idx = productCount++;
    $('#product-list').append(makeProductRow(idx, item));
    $(`#product-list .product-select[data-idx="${idx}"]`).select2({
        placeholder:'— Select Product —', allowClear:true,
        width:'100%', dropdownParent:$('#addSaleModal')
    }).on('change', function(){ updateRowPreview(idx); updateTotal(); });
    if (item) setTimeout(() => { updateRowPreview(idx); }, 120);
    updateTotal();
}

function removeRow(idx) {
    if ($('.product-row').length <= 1) { alert('At least one product is required.'); return; }
    $(`#prow_${idx}, #preview_${idx}`).remove();
    updateTotal();
}

function updateRowPreview(idx) {
    const sel = $(`#product-list .product-select[data-idx="${idx}"]`);
    const opt = sel.find('option:selected');
    const qty = parseFloat($(`#product-list .qty-input[data-idx="${idx}"]`).val() || 0);
    const disc = parseFloat($(`#product-list .disc-input[data-idx="${idx}"]`).val() || 0) / 100;
    const excl = parseFloat(opt.data('excl') || 0);
    const sgst = parseFloat(opt.data('sgst') || 0) / 100;
    const cgst = parseFloat(opt.data('cgst') || 0) / 100;
    const igst = parseFloat(opt.data('igst') || 0) / 100;
    const prev = $(`#preview_${idx}`);
    if (!sel.val() || qty <= 0) { prev.removeClass('show'); return; }

    const grossExcl = excl * qty;
    const discAmt = grossExcl * disc;
    const taxable = grossExcl - discAmt;
    const sgstAmt = taxable * sgst;
    const cgstAmt = taxable * cgst;
    const igstAmt = taxable * igst;
    const taxAmt = sgstAmt + cgstAmt + igstAmt;
    const lineTotal = taxable + taxAmt;

    $(`#pp_excl_${idx}`).text('₹' + grossExcl.toFixed(2));
    $(`#pp_taxable_${idx}`).text('₹' + taxable.toFixed(2));

    let taxHtml = '';
    if (sgstAmt > 0) taxHtml += `<span class="pp-tax-chip tax-sgst">SGST ${parseFloat(opt.data('sgst')||0).toFixed(1)}% ₹${sgstAmt.toFixed(2)}</span>`;
    if (cgstAmt > 0) taxHtml += `<span class="pp-tax-chip tax-cgst">CGST ${parseFloat(opt.data('cgst')||0).toFixed(1)}% ₹${cgstAmt.toFixed(2)}</span>`;
    if (igstAmt > 0) taxHtml += `<span class="pp-tax-chip tax-igst">IGST ${parseFloat(opt.data('igst')||0).toFixed(1)}% ₹${igstAmt.toFixed(2)}</span>`;
    if (taxAmt === 0) taxHtml += `<span class="pp-tax-chip tax-rate-chip">GST 0%</span>`;
    $(`#pp_taxdetail_${idx}`).html(taxHtml);

    if (disc > 0) { $(`#pp_disc_row_${idx}`).show(); $(`#pp_disc_${idx}`).text('– ₹' + discAmt.toFixed(2)); }
    else $(`#pp_disc_row_${idx}`).hide();

    $(`#pp_total_${idx}`).text('₹' + lineTotal.toFixed(2));
    prev.addClass('show');
}

function updateTotal() {
    let subtotal=0, discount=0, taxable=0, sgstSum=0, cgstSum=0, igstSum=0, taxSum=0, total=0;

    $('.product-row').each(function(){
        const sel = $(this).find('.product-select');
        const opt = sel.find('option:selected');
        const qty = parseFloat($(this).find('.qty-input').val() || 0);
        const disc = parseFloat($(this).find('.disc-input').val() || 0) / 100;
        const excl = parseFloat(opt.data('excl') || 0);
        const sgst = parseFloat(opt.data('sgst') || 0) / 100;
        const cgst = parseFloat(opt.data('cgst') || 0) / 100;
        const igst = parseFloat(opt.data('igst') || 0) / 100;
        if (!sel.val() || qty <= 0) return;
        const gross = excl * qty;
        const discAmt = gross * disc;
        const tax_able = gross - discAmt;
        const sa = tax_able * sgst, ca = tax_able * cgst, ia = tax_able * igst;
        subtotal += gross;
        discount += discAmt;
        taxable += tax_able;
        sgstSum += sa;
        cgstSum += ca;
        igstSum += ia;
        taxSum += (sa + ca + ia);
        total += tax_able + sa + ca + ia;
    });

    $('#td_subtotal').text('₹' + subtotal.toFixed(2));
    $('#td_discount').text('– ₹' + discount.toFixed(2));
    $('#td_taxable').text('₹' + taxable.toFixed(2));
    $('#td_sgst').text('₹' + sgstSum.toFixed(2));
    $('#td_cgst').text('₹' + cgstSum.toFixed(2));
    $('#td_igst').text('₹' + igstSum.toFixed(2));
    $('#td_tax').text('₹' + taxSum.toFixed(2));
    $('#td_total').text('₹' + total.toFixed(2));

    sgstSum > 0 ? $('#td_sgst_row').show() : $('#td_sgst_row').hide();
    cgstSum > 0 ? $('#td_cgst_row').show() : $('#td_cgst_row').hide();
    igstSum > 0 ? $('#td_igst_row').show() : $('#td_igst_row').hide();

    const tdsPercent = parseFloat($('#tds_percent').val() || 0);
    const tdsAmount = total * (tdsPercent / 100);
    const finalPayable = total - tdsAmount;

    $('#tds_percent_lbl').text(tdsPercent);
    $('#td_tds').text('– ₹' + tdsAmount.toFixed(2));
    $('#tds_amount_display').val('₹' + tdsAmount.toFixed(2));
    if (tdsPercent > 0) $('#td_tds_row').show(); else $('#td_tds_row').hide();

    $('#td_total').text('₹' + finalPayable.toFixed(2));

    const paid = parseFloat($('#paid_amount').val() || 0);
    const hint = $('#paymentHint');
    hint.show().removeClass('full partial none');
    if (finalPayable <= 0) { hint.hide(); return finalPayable; }
    const due = finalPayable - paid;

    if (paid <= 0) {
        hint.addClass('none').html('<i class="fas fa-clock me-1"></i>No payment — Invoice will be <strong>Pending</strong>');
    } else if (due > 0.005) {
        hint.addClass('partial').html('<i class="fas fa-circle-half-stroke me-1"></i>Due ₹' + due.toFixed(2) + ' — Invoice will be <strong>Partial</strong>');
    } else {
        hint.addClass('full').html('<i class="fas fa-check-circle me-1"></i>Fully paid — Invoice will be <strong>Paid</strong>');
    }
    return total;
}

$(function(){
    function initCustomerSelect2() {
        if (!$.fn.select2) return;
        $('#customer_name_select').select2({placeholder:'Search or select customer…', allowClear:true, width:'100%', dropdownParent:$('#addSaleModal')});
    }

    $(document).on('change','#customer_name_select', function(){
        const opt = $(this).find('option:selected');
        $('#customer_phone_add').val(opt.data('phone') || '');
        $('#customer_address_add').val(opt.data('address') || '');
    });

    $(document).on('input','#paid_amount', updateTotal);
    $(document).on('input','.qty-input, .disc-input', function(){
        const idx = $(this).data('idx');
        updateRowPreview(idx); updateTotal();
    });

    $('#addProductBtn').on('click', function(){ addRow(null); });

    $('#addSaleModal').on('shown.bs.modal', function(){
        initCustomerSelect2();
        if ($('.product-row').length === 0) addRow(null);
    });

    $('#addSaleModal').on('hidden.bs.modal', function(){
        $('#addSaleForm')[0].reset();
        try { $('#customer_name_select').val(null).trigger('change'); } catch(e){}
        $('#product-list').empty();
        productCount = 0;
        $('#products_json, #invoice_id').val('');
        $('#customer_phone_add, #customer_address_add, #paid_amount, #payment_method').val('');
        $('#paymentHint').hide();
        $('#saleModalTitle').html('<i class="fas fa-plus-circle me-2"></i>New Sale');
        $('#saleSubmitBtn').attr('name','add_sale');
        $('#saleSubmitText').text('Save Sale');
        updateTotal();
    });

    $('#addSaleForm').on('submit', function(e){
        const products = []; let hasError = false;
        $('.product-row').each(function(){
            const pid = $(this).find('.product-select').val();
            const qty = parseInt($(this).find('.qty-input').val() || 0);
            const disc = parseFloat($(this).find('.disc-input').val() || 0);
            const ttype = $(this).find('.type-select').val() || 'sale';
            const serials = $(this).find('.serials-input').val() || '';
            if (!pid || qty <= 0) { hasError = true; return; }
            products.push({product_id: pid, quantity: qty, discount: disc, transaction_type: ttype, serial_numbers: serials});
        });
        if (hasError || !products.length) {
            e.preventDefault(); alert('Please select a product and enter valid quantity for all rows.'); return false;
        }
        const total = updateTotal();
        const paid = parseFloat($('#paid_amount').val() || 0);
        if (paid > 0 && paid > total + 0.01) {
            e.preventDefault(); alert('Paid amount (₹' + paid.toFixed(2) + ') cannot exceed invoice total (₹' + total.toFixed(2) + ').'); return false;
        }
        $('#products_json').val(JSON.stringify(products));
        return true;
    });

    $(document).on('click', '.whatsapp', function(e){
        e.preventDefault();
        try {
            const ids = $(this).data('sale-ids');
            const sale = salesData.find(s => JSON.stringify(s.ids) === JSON.stringify(ids));
            if (!sale) { alert('Sale data not found!'); return; }
            let phone = (sale.customer_phone || '').replace(/\D/g, '');
            if (phone.length === 10) phone = '91' + phone;
            if (!phone) { alert('Phone not available.'); return; }
            const invoiceLink = 'https://growbiller.com/sale_invoice.php?invoice_id=' + sale.invoice_id;
            const prods = sale.products.map(p => '  • ' + p.product_name + ' x ' + p.quantity).join('\n');
            const msg = 'Hello ' + sale.customer_name + '\n\nYour invoice is ready!\n\nInvoice #' + sale.invoice_id + '\nProducts:\n' + prods + '\nTotal: ₹' + sale.total_price.toFixed(2) + '\nPaid: ₹' + sale.paid_amount.toFixed(2) + '\n\nView Invoice:\n' + invoiceLink + '\n\nThank you!';
            window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
        } catch(err) { console.error(err); alert('WhatsApp failed!'); }
    });

    const sAlert = document.getElementById('successAlert');
    if (sAlert) setTimeout(() => { sAlert.style.transition='opacity .5s'; sAlert.style.opacity='0'; setTimeout(()=>sAlert.remove(),500); }, 4000);
    updateTableCount();
});

function editSale(sale) {
    try {
        $('#invoice_id').val(sale.invoice_id || (sale.ids && sale.ids.length ? sale.ids[0] : ''));
        const cname = sale.customer_name || '';
        const $sel = $('#customer_name_select');
        if (cname && !$sel.find('option[value="' + cname + '"]').length)
            $sel.append(new Option(cname, cname, true, true));
        try { $sel.val(cname).trigger('change'); } catch(e) { $sel.val(cname); }
        $('#customer_phone_add').val(sale.customer_phone || '');
        $('#customer_address_add').val(sale.customer_address || '');
        $('#product-list').empty();
        productCount = 0;
        (sale.products || []).forEach(p => addRow(p));
        $('#payment_method').val(sale.payment_methods ? sale.payment_methods.split(',')[0].trim() : '');
        $('#paid_amount').val(sale.paid_amount || '');
        $('#saleModalTitle').html('<i class="fas fa-pen me-2"></i>Edit Sale');
        $('#saleSubmitBtn').attr('name','edit_sale');
        $('#saleSubmitText').text('Update Sale');
        
        $('#terms_text').val(sale.terms_text || '');
        const matchingTerm = termsList.find(t => t.content === (sale.terms_text || '').trim());
        if (matchingTerm) {
            $('#terms_title_select').val(matchingTerm.id);
        } else {
            $('#terms_title_select').val('');
        }
        
        setTimeout(updateTotal, 200);
    } catch(err) { console.error(err); alert('Failed to load sale: ' + err.message); }
}

function openPaymentModal(invoiceId, outstanding, customerName) {
    payOutstandingVal = outstanding;
    document.getElementById('payInvoiceId').value = invoiceId;
    document.getElementById('payOutstanding').textContent = '₹' + outstanding.toFixed(2);
    document.getElementById('payCustomerName').textContent = customerName;
    document.getElementById('payAmountInput').value = outstanding.toFixed(2);
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}
function setFullPayment() { document.getElementById('payAmountInput').value = payOutstandingVal.toFixed(2); }
function setHalfPayment() { document.getElementById('payAmountInput').value = (payOutstandingVal / 2).toFixed(2); }

function filterTable() {
    const q = (document.getElementById('tableSearch').value || '').toLowerCase();
    const status = document.getElementById('statusFilter').value;
    let visible = 0;
    document.querySelectorAll('#salesTableBody tr[data-search]').forEach(tr => {
        const matchQ = !q || tr.dataset.search.includes(q);
        const matchS = !status || tr.dataset.status === status;
        tr.style.display = (matchQ && matchS) ? '' : 'none';
        if (matchQ && matchS) visible++;
    });
    updateTableCount(visible);
}
function updateTableCount(n) {
    const total = document.querySelectorAll('#salesTableBody tr[data-search]').length;
    const count = (n === undefined) ? total : n;
    const el = document.getElementById('tableCount');
    if (el) el.textContent = count + ' record' + (count !== 1 ? 's' : '');
}

function showExportModal(type) {
    currentExportType = type; selectedDuration = ''; customRange = null;
    document.querySelectorAll('.duration-card').forEach(c => c.classList.remove('active'));
    document.getElementById('proceedExport').disabled = true;
    document.getElementById('exportBtnText').textContent = 'Export';
    document.getElementById('exportModalTitle').innerHTML = '<i class="fas fa-download me-2"></i>Export as ' + type.toUpperCase();
    new bootstrap.Modal(document.getElementById('exportModal')).show();
}
function selectDuration(el, dur) {
    document.querySelectorAll('.duration-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active'); selectedDuration = dur; customRange = null;
    document.getElementById('exportFrom').value = '';
    document.getElementById('exportTo').value = '';
    document.getElementById('proceedExport').disabled = false;
    document.getElementById('exportBtnText').textContent = 'Export ' + currentExportType.toUpperCase();
}
function applyCustomRange() {
    const f = document.getElementById('exportFrom').value;
    const t = document.getElementById('exportTo').value;
    if (!f || !t) { alert('Select both dates.'); return; }
    if (new Date(f) > new Date(t)) { alert('Start date cannot be after end date.'); return; }
    document.querySelectorAll('.duration-card').forEach(c => c.classList.remove('active'));
    selectedDuration = 'custom'; customRange = {start:f, end:t};
    document.getElementById('proceedExport').disabled = false;
    document.getElementById('exportBtnText').textContent = 'Export ' + currentExportType.toUpperCase() + ' (Custom)';
}
function getFilteredData() {
    if (selectedDuration === 'all') return salesData;
    if (selectedDuration === 'custom') {
        const s = new Date(customRange.start); const e = new Date(customRange.end); e.setHours(23,59,59,999);
        return salesData.filter(d => { const dt = new Date(d.created_at); return dt >= s && dt <= e; });
    }
    const cutoff = new Date(Date.now() - parseInt(selectedDuration) * 86400000);
    return salesData.filter(d => new Date(d.created_at) >= cutoff);
}
function doExport() {
    const data = getFilteredData();
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
    if (currentExportType === 'pdf') exportPDF(data);
    else exportExcel(data);
}
function exportPDF(data) {
    const {jsPDF} = window.jspdf; const doc = new jsPDF();
    const pw = doc.internal.pageSize.width;
    doc.setFillColor(2,132,199); doc.rect(0,0,pw,30,'F');
    doc.setTextColor(255,255,255); doc.setFontSize(16); doc.setFont(undefined,'bold');
    doc.text('Sales Report', pw/2, 14, {align:'center'});
    doc.setFontSize(9); doc.setFont(undefined,'normal');
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN') + ' | Records: ' + data.length, pw/2, 25, {align:'center'});
    doc.setTextColor(30,30,30);
    const rows = data.map((s,i) => [
        i+1, '#'+s.invoice_id, s.customer_name, s.customer_phone||'—',
        s.products.map(p=>p.product_name + '(x' + p.quantity + ')').join(', '),
        '₹'+s.total_excl.toFixed(2),
        '₹'+s.total_sgst.toFixed(2), '₹'+s.total_cgst.toFixed(2), '₹'+s.total_igst.toFixed(2),
        '₹'+s.total_price.toFixed(2), '₹'+s.paid_amount.toFixed(2),
        s.payment_status.toUpperCase(), new Date(s.created_at).toLocaleDateString('en-IN'), s.created_by_name
    ]);
    doc.autoTable({
        head:[['#','Invoice','Customer','Phone','Products','Taxable','SGST','CGST','IGST','Total','Paid','Status','Date','By']],
        body: rows, startY:38,
        styles:{fontSize:6.5, cellPadding:2},
        headStyles:{fillColor:[2,132,199], textColor:255, fontStyle:'bold'},
        alternateRowStyles:{fillColor:[240,249,255]},
        margin:{left:5, right:5}
    });
    doc.save('sales-' + (selectedDuration === 'custom' ? customRange.start + '_' + customRange.end : selectedDuration) + '.pdf');
}
function exportExcel(data) {
    const ws_data = [['#','Invoice','Customer','Phone','Address','Products','Taxable Amt','SGST','CGST','IGST','Total GST','Total (incl. GST)','Paid','Outstanding','Status','Date','Created By']];
    data.forEach((s,i) => {
        ws_data.push([
            i+1, '#'+s.invoice_id, s.customer_name, s.customer_phone||'', s.customer_address||'',
            s.products.map(p=>p.product_name + 'x' + p.quantity).join('; '),
            s.total_excl.toFixed(2), s.total_sgst.toFixed(2), s.total_cgst.toFixed(2), s.total_igst.toFixed(2),
            s.total_tax.toFixed(2), s.total_price.toFixed(2), s.paid_amount.toFixed(2), s.outstanding.toFixed(2),
            s.payment_status, new Date(s.created_at).toLocaleDateString('en-IN'), s.created_by_name
        ]);
    });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb,'Sales',ws);
    XLSX.writeFile(wb, 'sales-' + (selectedDuration === 'custom' ? customRange.start + '_' + customRange.end : selectedDuration) + '.xlsx');
}

// --- TERMS & CONDITIONS LOGIC ---
let termsList = [];

function fetchTerms() {
    $.post('sales.php', { action: 'manage_terms', operation: 'fetch' }, function(res) {
        if (res.status === 'success') {
            termsList = res.data;
            updateTermsDropdowns();
        }
    }, 'json');
}

function updateTermsDropdowns() {
    const $mainSel = $('#terms_title_select');
    const $manageSel = $('#manage_terms_select');
    
    // Store selected value
    const currentMain = $mainSel.val();
    
    $mainSel.empty().append('<option value="">None</option>');
    $manageSel.empty().append('<option value="">-- Add New Term --</option>');
    
    termsList.forEach(t => {
        $mainSel.append(`<option value="${t.id}">${t.title}</option>`);
        $manageSel.append(`<option value="${t.id}">${t.title}</option>`);
    });
    
    // Restore selection if exists
    if ($mainSel.find(`option[value="${currentMain}"]`).length) {
        $mainSel.val(currentMain);
    }
}
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
function prepareManageTermsModal() {
    $('#manage_term_id').val('');
    $('#manage_term_title').val('');
    $('#manage_term_content').val('');
    $('#manage_terms_select').val('');
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

function loadTermToEdit() {
    const termId = $('#manage_terms_select').val();
    if (!termId) {
        $('#manage_term_id').val('');
        $('#manage_term_title').val('');
        $('#manage_term_content').val('');
        $('#btnDeleteTerm').hide();
        return;
    }
    const term = termsList.find(t => t.id == termId);
    if (term) {
        $('#manage_term_id').val(term.id);
        $('#manage_term_title').val(term.title);
        $('#manage_term_content').val(term.content);
        $('#btnDeleteTerm').show();
    }
}

function saveTerm() {
    try {
        const id = $('#manage_term_id').val();
        const title = $('#manage_term_title').val();
        const content = $('#manage_term_content').val();
        
        if (!title || !title.trim() || !content || !content.trim()) {
            alert("Title and content are required.");
            return;
        }
        
        const op = id ? 'edit' : 'add';
        const payload = { action: 'manage_terms', operation: op, title: title.trim(), content: content.trim() };
        if (id) payload.id = id;
        
        // Show loading state on button
        const $btn = $('#manageTermsModal .btn-primary-custom');
        const origText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        $btn.prop('disabled', true);
        
        $.post('sales.php', payload, function(res) {
            $btn.html(origText).prop('disabled', false);
            if (res && res.status === 'success') {
                fetchTerms();
                // Safely hide the modal by clicking its own cancel button
                $('#manageTermsModal').find('.btn-secondary[data-bs-dismiss="modal"]').click();
                
                setTimeout(() => {
                    const newId = id ? id : res.id;
                    $('#terms_title_select').val(newId);
                    applyTermContent();
                }, 300);
            } else {
                alert((res && res.message) ? res.message : "Failed to save term (unknown server error).");
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            $btn.html(origText).prop('disabled', false);
            alert("Network/Server Error: " + textStatus + "\n" + jqXHR.responseText.substring(0, 100));
        });
    } catch (e) {
        alert("Javascript Error: " + e.message);
    }
}

function deleteTerm() {
    const id = $('#manage_term_id').val();
    if (!id) return;
    if (!confirm("Are you sure you want to delete this term?")) return;
    
    $.post('sales.php', { action: 'manage_terms', operation: 'delete', id: id }, function(res) {
        if (res.status === 'success') {
            fetchTerms();
            // Safely hide the modal by clicking its own cancel button
            $('#manageTermsModal').find('.btn-secondary[data-bs-dismiss="modal"]').click();
            if ($('#terms_title_select').val() == id) {
                $('#terms_title_select').val('');
                $('#terms_text').val('');
            }
        } else {
            alert(res.message || "Failed to delete term.");
        }
    }, 'json');
}

$(document).ready(function() {
    fetchTerms();
});

// Update reset form to clear terms
const origResetForm = window.resetForm;
window.resetForm = function() {
    if (origResetForm) origResetForm();
    $('#terms_title_select').val('');
    $('#terms_text').val('');
};
