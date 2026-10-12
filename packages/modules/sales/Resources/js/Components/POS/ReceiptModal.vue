<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCurrency } from '@/Composables/useCurrency';
import Modal from '@tessera/tessera-ui/Components/Modal.vue';
import axios from 'axios';
import { FileDown, Printer, X } from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    order: {
        type: Object,
        default: null,
    },
    currency: {
        type: String,
        default: 'USD',
    },
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const { formatCurrency } = useCurrency();

const escapeHtml = (text) => {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

const receiptData = ref(null);
const isLoading = ref(false);

const loadReceipt = async () => {
    if (!props.order) return;
    
    if (props.order.offline) {
        // For offline orders, create receipt data from order object
        receiptData.value = {
            tenant_name: 'Store',
            branch_name: 'Branch',
            branch_address: null,
            receipt_header: '',
            receipt_footer: '',
            logo_url: '',
            order_number: props.order.order_number,
            date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
            time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
            cashier: 'Cashier',
            items: props.order.items || [],
            subtotal: props.order.subtotal || props.order.total,
            discount_amount: props.order.discount_amount || 0,
            total: props.order.total,
            cash_received: props.order.total + (props.order.change || 0),
            change: props.order.change || 0,
        };
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get(route('pos.receipt', props.order.id));
        receiptData.value = response.data;
    } catch (error) {
        console.error('Failed to load receipt:', error);
    } finally {
        isLoading.value = false;
    }
};

const printReceipt = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        // Fallback if popup blocked
        const receiptHtml = generateReceiptHtml();
        const printDoc = document.createElement('iframe');
        printDoc.style.position = 'absolute';
        printDoc.style.width = '0';
        printDoc.style.height = '0';
        printDoc.style.border = 'none';
        document.body.appendChild(printDoc);
        printDoc.contentDocument.write(receiptHtml);
        printDoc.contentDocument.close();
        printDoc.contentWindow.focus();
        setTimeout(() => {
            printDoc.contentWindow.print();
            document.body.removeChild(printDoc);
        }, 250);
        return;
    }

    const receiptHtml = generateReceiptHtml();
    printWindow.document.write(receiptHtml);
    printWindow.document.close();
    printWindow.focus();
    
    // Wait for content to load before printing
    setTimeout(() => {
        printWindow.print();
        // Don't close immediately - let user cancel if needed
        setTimeout(() => {
            printWindow.close();
        }, 1000);
    }, 250);
};

const generateReceiptHtml = () => {
    const data = receiptData.value || {};
    const header = data.receipt_header || '';
    const footer = data.receipt_footer || '';
    const currencyCode = props.currency || 'USD';
    
    return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - ${data.order_number}</title>
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 10mm;
            }
            .no-print {
                display: none;
            }
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            max-width: 80mm;
            margin: 0 auto;
            padding: 10mm;
            color: #000; /* allow-colour: printed receipt document */
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            white-space: pre-line;
        }
        .tenant-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .branch-info {
            font-size: 11px;
            margin-bottom: 10px;
        }
        .divider {
            border-top: 1px dashed #000; /* allow-colour */
            margin: 10px 0;
        }
        .order-info {
            margin-bottom: 10px;
        }
        .order-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }
        .items-table {
            width: 100%;
            margin: 10px 0;
        }
        .item-row {
            margin-bottom: 5px;
            font-size: 11px;
        }
        .item-name {
            margin-bottom: 2px;
        }
        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #666; /* allow-colour */
        }
        .totals {
            margin-top: 10px;
            border-top: 1px dashed #000; /* allow-colour */
            padding-top: 10px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .total-label {
            font-weight: bold;
        }
        .grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 1px solid #000; /* allow-colour */
            border-bottom: 1px solid #000; /* allow-colour */
            padding: 5px 0;
            margin: 10px 0;
        }
        .payment-info {
            margin-top: 10px;
            font-size: 11px;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 15px;
            white-space: pre-line;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="receipt-header">
        <div class="tenant-name">${escapeHtml(data.tenant_name || 'Store')}</div>
        ${data.branch_name ? `<div class="branch-info">${escapeHtml(data.branch_name)}</div>` : ''}
        ${data.branch_address ? `<div class="branch-info">${escapeHtml(data.branch_address)}</div>` : ''}
        ${data.receipt_header ? `<div class="branch-info">${escapeHtml(data.receipt_header)}</div>` : ''}
    </div>
    <div class="divider"></div>
    
    <div class="order-info">
        <div class="order-info-row">
            <span>Order #:</span>
            <span>${escapeHtml(data.order_number || '')}</span>
        </div>
        <div class="order-info-row">
            <span>Date:</span>
            <span>${escapeHtml(data.date || '')}</span>
        </div>
        <div class="order-info-row">
            <span>Time:</span>
            <span>${escapeHtml(data.time || '')}</span>
        </div>
        <div class="order-info-row">
            <span>Cashier:</span>
            <span>${escapeHtml(data.cashier || '')}</span>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div class="items-table">
        ${(data.items || []).map(item => `
            <div class="item-row">
                <div class="item-name">${escapeHtml(item.name)}</div>
                <div class="item-details">
                    <span>${item.quantity} x ${formatCurrency(item.unit_price, currencyCode)}</span>
                    <span>${formatCurrency(item.total, currencyCode)}</span>
                </div>
            </div>
        `).join('')}
    </div>
    
    <div class="divider"></div>
    
    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>${formatCurrency(data.subtotal || 0, currencyCode)}</span>
        </div>
        ${data.discount_amount > 0 ? `
        <div class="total-row">
            <span>Discount:</span>
            <span>-${formatCurrency(data.discount_amount, currencyCode)}</span>
        </div>
        ` : ''}
        <div class="total-row grand-total">
            <span>Total:</span>
            <span>${formatCurrency(data.total || 0, currencyCode)}</span>
        </div>
        <div class="payment-info">
            <div class="total-row">
                <span>Cash:</span>
                <span>${formatCurrency(data.cash_received || 0, currencyCode)}</span>
            </div>
            <div class="total-row">
                <span>Change:</span>
                <span>${formatCurrency(data.change || 0, currencyCode)}</span>
            </div>
        </div>
    </div>
    
    ${footer ? `
    <div class="divider"></div>
    <div class="receipt-footer">
        ${escapeHtml(footer).replace(/\n/g, '<br>')}
    </div>
    ` : ''}
</body>
</html>
    `;
};

const downloadPDF = async () => {
    try {
        // Dynamically import jsPDF
        const { default: jsPDF } = await import('jspdf');
        const data = receiptData.value || {};
        const currencyCode = props.currency || 'USD';
        const doc = new jsPDF({
            unit: 'mm',
            format: [80, 200],
            compress: true,
        });

        let y = 10;
        const pageWidth = 80;
        const margin = 5;
        const maxWidth = pageWidth - (margin * 2);

    // Header
    if (data.receipt_header) {
        doc.setFontSize(8);
        const headerLines = doc.splitTextToSize(data.receipt_header, maxWidth);
        doc.text(headerLines, margin, y, { align: 'center' });
        y += headerLines.length * 4;
    }

    // Tenant name
    doc.setFontSize(12);
    doc.setFont(undefined, 'bold');
    doc.text(data.tenant_name || 'Store', pageWidth / 2, y, { align: 'center' });
    y += 6;

    // Branch info
    if (data.branch_name) {
        doc.setFontSize(9);
        doc.setFont(undefined, 'normal');
        doc.text(data.branch_name, pageWidth / 2, y, { align: 'center' });
        y += 4;
    }
    if (data.branch_address) {
        doc.setFontSize(8);
        const addressLines = doc.splitTextToSize(data.branch_address, maxWidth);
        doc.text(addressLines, pageWidth / 2, y, { align: 'center' });
        y += addressLines.length * 3;
    }

    y += 3;
    doc.line(margin, y, pageWidth - margin, y);
    y += 5;

    // Order info
    doc.setFontSize(9);
    doc.text(`Order #: ${data.order_number || ''}`, margin, y);
    y += 4;
    doc.text(`Date: ${data.date || ''}`, margin, y);
    y += 4;
    doc.text(`Time: ${data.time || ''}`, margin, y);
    y += 4;
    doc.text(`Cashier: ${data.cashier || ''}`, margin, y);
    y += 5;

    doc.line(margin, y, pageWidth - margin, y);
    y += 5;

    // Items
    doc.setFontSize(8);
    (data.items || []).forEach(item => {
        const itemName = doc.splitTextToSize(item.name, maxWidth - 20);
        doc.text(itemName, margin, y);
        y += itemName.length * 3;
        doc.text(`${item.quantity} x ${formatCurrency(item.unit_price, currencyCode)}`, margin + 5, y);
        doc.text(formatCurrency(item.total, currencyCode), pageWidth - margin, y, { align: 'right' });
        y += 4;
    });

    y += 3;
    doc.line(margin, y, pageWidth - margin, y);
    y += 5;

    // Totals
    doc.setFontSize(9);
    doc.text('Subtotal:', margin, y);
    doc.text(formatCurrency(data.subtotal || 0, currencyCode), pageWidth - margin, y, { align: 'right' });
    y += 4;

    if (data.discount_amount > 0) {
        doc.text('Discount:', margin, y);
        doc.text(`-${formatCurrency(data.discount_amount, currencyCode)}`, pageWidth - margin, y, { align: 'right' });
        y += 4;
    }

    doc.setFont(undefined, 'bold');
    doc.setFontSize(10);
    doc.text('Total:', margin, y);
    doc.text(formatCurrency(data.total || 0, currencyCode), pageWidth - margin, y, { align: 'right' });
    y += 6;

    doc.setFont(undefined, 'normal');
    doc.setFontSize(9);
    doc.text('Cash:', margin, y);
    doc.text(formatCurrency(data.cash_received || 0, currencyCode), pageWidth - margin, y, { align: 'right' });
    y += 4;
    doc.text('Change:', margin, y);
    doc.text(formatCurrency(data.change || 0, currencyCode), pageWidth - margin, y, { align: 'right' });
    y += 6;

    // Footer
    if (data.receipt_footer) {
        doc.line(margin, y, pageWidth - margin, y);
        y += 5;
        doc.setFontSize(8);
        const footerLines = doc.splitTextToSize(data.receipt_footer, maxWidth);
        doc.text(footerLines, pageWidth / 2, y, { align: 'center' });
        y += footerLines.length * 3;
    }

    y += 3;
    doc.line(margin, y, pageWidth - margin, y);
    y += 5;
    doc.setFontSize(8);
    doc.text('Thank you for your purchase!', pageWidth / 2, y, { align: 'center' });

    doc.save(`receipt-${data.order_number || 'order'}.pdf`);
    } catch (error) {
        console.error('Failed to generate PDF:', error);
        // Fallback to print dialog
        printReceipt();
    }
};

watch(() => props.show, (show) => {
    if (show) {
        loadReceipt();
    }
});
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="md">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <h2 class="font-display text-xl font-normal text-fg">
                    {{ t('pos.receiptTitle') }}
                </h2>
                <button
                    type="button"
                    @click="emit('close')"
                    class="text-faint hover:text-fg"
                >
                    <X class="h-6 w-6" />
                </button>
            </div>

            <!-- Receipt Preview -->
            <div v-if="receiptData" class="mb-6 max-w-md rounded-2xl border bg-surface p-4 font-mono text-sm print:max-w-none print:border-0 print:shadow-none">
                <!-- Receipt Header -->
                <div v-if="receiptData.receipt_header" class="mb-3 whitespace-pre-line text-center text-xs">
                    {{ receiptData.receipt_header }}
                </div>
                <div v-if="!receiptData.tenant_logo" class="mb-2 text-center font-bold text-base">
                    {{ receiptData.tenant_name || 'Store' }}
                </div>
                <div v-else class="flex mb-2 justify-center">
                    <img :src="receiptData.tenant_logo" alt="" class="h-12 w-12 rounded-lg object-cover" />
                </div>
                <div v-if="receiptData.branch_name" class="mb-1 text-center text-xs">
                    {{ receiptData.branch_name }}
                </div>
                <div v-if="receiptData.branch_address" class="mb-3 text-center text-xs">
                    {{ receiptData.branch_address }}
                </div>
                <!-- <div v-if="receiptData.receipt_header" class="mb-3 text-center text-xs">
                    {{ receiptData.receipt_header }}
                </div> -->


                <div class="my-3 border-t border-dashed border-line-strong"></div>
                
                <!-- Order Info -->
                <div class="mb-2 space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span>Order #:</span>
                        <span>{{ receiptData.order_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Date:</span>
                        <span>{{ receiptData.date }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Time:</span>
                        <span>{{ receiptData.time }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Cashier:</span>
                        <span>{{ receiptData.cashier }}</span>
                    </div>
                </div>
                
                <div class="my-3 border-t border-dashed border-line-strong"></div>
                
                <!-- Items -->
                <div v-if="receiptData.items && receiptData.items.length > 0" class="mb-3 space-y-2">
                    <div v-for="(item, index) in receiptData.items" :key="index" class="text-xs">
                        <div class="font-medium">{{ item.name }}</div>
                        <div class="flex justify-between text-muted">
                            <span>{{ item.quantity }} x {{ formatCurrency(item.unit_price, props.currency) }}</span>
                            <span>{{ formatCurrency(item.total, props.currency) }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="mb-3 text-xs text-muted">
                    No items
                </div>
                
                <div class="my-3 border-t border-dashed border-line-strong"></div>
                
                <!-- Totals -->
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span>{{ formatCurrency(receiptData.subtotal || 0, props.currency) }}</span>
                    </div>
                    <div v-if="receiptData.discount_amount > 0" class="flex justify-between text-status-success">
                        <span>Discount:</span>
                        <span>-{{ formatCurrency(receiptData.discount_amount, props.currency) }}</span>
                    </div>
                    <div class="my-2 flex justify-between border-t border-b border-line-strong py-1 font-bold">
                        <span>Total:</span>
                        <span>{{ formatCurrency(receiptData.total || 0, props.currency) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Cash:</span>
                        <span>{{ formatCurrency(receiptData.cash_received || 0, props.currency) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Change:</span>
                        <span>{{ formatCurrency(receiptData.change || 0, props.currency) }}</span>
                    </div>
                </div>
                
                <!-- Receipt Footer -->
                <div v-if="receiptData.receipt_footer" class="mt-4 whitespace-pre-line border-t border-dashed border-line-strong pt-3 text-center text-xs">
                    {{ receiptData.receipt_footer }}
                </div>
                <div v-else>
                    <div class="my-3 border-t border-dashed border-line-strong"></div>
                    <div class="text-center text-xs">Thank you for your purchase!</div>
                </div>
                
            </div>

            <!-- Loading State -->
            <div v-else-if="isLoading" class="mb-6 text-center text-muted">
                {{ t('common.loading') }}
            </div>

            <!-- Offline Notice -->
            <div v-if="order?.offline" class="mb-4 rounded-lg bg-status-warn-wash p-3 text-center text-sm text-status-warn">
                {{ t('offline.workingOffline') }}
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button
                    type="button"
                    @click="printReceipt"
                    class="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-line bg-surface px-4 py-3 font-medium text-muted transition-colors hover:bg-hover-wash"
                >
                    <Printer class="h-5 w-5" />
                    {{ t('pos.printReceipt') }}
                </button>
                <!-- <button
                    v-if="receiptData"
                    type="button"
                    @click="downloadPDF"
                    class="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-line bg-surface px-4 py-3 font-medium text-muted transition-colors hover:bg-hover-wash"
                >
                    <FileDown class="h-5 w-5" />
                    {{ t('pos.downloadPDF') }}
                </button> -->
                <button
                    type="button"
                    @click="emit('close')"
                    class="flex-1 rounded-lg bg-gold px-4 py-3 font-medium text-ink transition-colors hover:bg-gold-deep"
                >
                    {{ t('pos.newSale') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
