import Chart from 'chart.js/auto';
import { HSStaticMethods } from 'preline';

export { Chart };

// Preline is used under its MIT + Preline UI Fair Use License.
const lifecycle = window.__operationalPrelineLifecycle ?? {
    initialized: false,
    alpineRegistered: false,
    cleanup: new Set(),
};

window.__operationalPrelineLifecycle = lifecycle;

const autoInit = () => {
    HSStaticMethods.autoInit();
    lifecycle.initialized = true;
};

const cleanUp = (element = document) => {
    lifecycle.cleanup.forEach((dispose) => dispose(element));
};

const scheduleAutoInit = () => window.requestAnimationFrame(autoInit);

const operationalModalController = () => ({
    requestLocked: false,
    opener: null,
    rememberOpener(opener) {
        this.opener = opener;
    },
    lockRequest() {
        this.requestLocked = true;
    },
    releaseRequest() {
        this.requestLocked = false;
    },
    closeModal(close) {
        if (this.requestLocked) return false;

        close();
        this.$nextTick(() => {
            if (this.opener) this.opener.focus();
        });

        return true;
    },
    focusFirstInvalid(root = this.$root) {
        this.$nextTick(() => {
            root?.querySelector('[aria-invalid="true"], :invalid')?.focus();
        });
    },
});

const registerOperationalModalController = () => {
    if (lifecycle.alpineRegistered || !window.Alpine) return;

    window.Alpine.data('operationalModal', operationalModalController);
    lifecycle.alpineRegistered = true;
};

document.addEventListener('DOMContentLoaded', autoInit);
document.addEventListener('alpine:init', registerOperationalModalController);
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.added', scheduleAutoInit);
    window.Livewire.hook('morph.removing', ({ el }) => cleanUp(el));
    window.Livewire.hook('morph.updated', scheduleAutoInit);
});
document.addEventListener('livewire:navigated', scheduleAutoInit);

export { HSStaticMethods, autoInit, cleanUp, operationalModalController };
