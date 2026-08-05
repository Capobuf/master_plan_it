import Chart from 'chart.js/auto';
import { HSStaticMethods } from 'preline';

export { Chart };

// Preline is used under its MIT + Preline UI Fair Use License.
const lifecycle = window.__operationalPrelineLifecycle ?? {
    initialized: false,
    alpineRegistered: false,
    documentListenersRegistered: false,
    livewireHooksRegistered: false,
    pendingFrame: null,
    cleanup: new Set(),
};

lifecycle.cleanup ??= new Set();
lifecycle.documentListenersRegistered ??= false;
lifecycle.livewireHooksRegistered ??= false;
lifecycle.pendingFrame ??= null;

window.__operationalPrelineLifecycle = lifecycle;

const autoInit = () => {
    if (lifecycle.pendingFrame !== null) {
        window.cancelAnimationFrame(lifecycle.pendingFrame);
        lifecycle.pendingFrame = null;
    }

    HSStaticMethods.autoInit();
    lifecycle.initialized = true;
};

const cleanUp = (element = document) => {
    lifecycle.cleanup.forEach((dispose) => dispose(element));
};

const scheduleAutoInit = () => {
    if (lifecycle.pendingFrame !== null) return;

    lifecycle.pendingFrame = window.requestAnimationFrame(() => {
        lifecycle.pendingFrame = null;
        autoInit();
    });
};

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

const registerLivewireHooks = () => {
    if (lifecycle.livewireHooksRegistered || !window.Livewire) return;

    window.Livewire.hook('morph.added', scheduleAutoInit);
    window.Livewire.hook('morph.removing', ({ el }) => cleanUp(el));
    window.Livewire.hook('morph.updated', scheduleAutoInit);
    lifecycle.livewireHooksRegistered = true;
};

if (!lifecycle.documentListenersRegistered) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleAutoInit, { once: true });
    } else {
        scheduleAutoInit();
    }

    document.addEventListener('alpine:init', registerOperationalModalController, { once: true });
    document.addEventListener('livewire:init', registerLivewireHooks, { once: true });
    document.addEventListener('livewire:navigated', scheduleAutoInit);
    lifecycle.documentListenersRegistered = true;
}

registerOperationalModalController();
registerLivewireHooks();

export { HSStaticMethods, autoInit, cleanUp, operationalModalController };
