import { useEffect } from "react";
import type { ReactNode } from "react";

type ModalProps = {
    isOpen: boolean;
    onClose: () => void;
    children: ReactNode;
    className?: string;
    showCloseButton?: boolean;
};

/** TailAdmin React Modal component. */
export default function Modal({ isOpen, onClose, children, className = "", showCloseButton = true }: ModalProps) {
    useEffect(() => {
        if (!isOpen) return;
        const closeOnEscape = (event: KeyboardEvent) => event.key === "Escape" && onClose();
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        document.addEventListener("keydown", closeOnEscape);
        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener("keydown", closeOnEscape);
        };
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4">
            <button aria-label="Close modal" className="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" onClick={onClose} />
            <div role="dialog" aria-modal="true" className={`relative w-full rounded-3xl bg-white shadow-theme-lg dark:bg-gray-900 ${className}`}>
                {showCloseButton && (
                    <button aria-label="Close modal" onClick={onClose} className="absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:hover:text-white">
                        <span aria-hidden="true" className="text-xl leading-none">×</span>
                    </button>
                )}
                {children}
            </div>
        </div>
    );
}
