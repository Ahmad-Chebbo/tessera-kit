import { ref, onMounted, onUnmounted } from 'vue';

export function useBarcode(onBarcodeScanned) {
    const barcodeBuffer = ref('');
    const lastKeyTime = ref(0);
    const BARCODE_TIMEOUT = 50; // ms between keystrokes for barcode scanner

    const handleKeyDown = (event) => {
        const currentTime = Date.now();

        // If it's been too long since the last keystroke, reset the buffer
        if (currentTime - lastKeyTime.value > BARCODE_TIMEOUT) {
            barcodeBuffer.value = '';
        }

        lastKeyTime.value = currentTime;

        // Handle Enter key (end of barcode)
        if (event.key === 'Enter') {
            if (barcodeBuffer.value.length >= 3) {
                // This looks like a barcode scan
                onBarcodeScanned(barcodeBuffer.value);
            }
            barcodeBuffer.value = '';
            return;
        }

        // Only accept alphanumeric characters for barcode
        if (event.key.length === 1 && /^[a-zA-Z0-9-]$/.test(event.key)) {
            // Don't capture if user is typing in an input field
            const activeElement = document.activeElement;
            const isInputField = activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.isContentEditable
            );

            // Allow barcode scanning even in search input (by checking for barcode-like speed)
            if (!isInputField || barcodeBuffer.value.length > 0) {
                barcodeBuffer.value += event.key;
            }
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeyDown);
    });

    return {
        barcodeBuffer,
    };
}
