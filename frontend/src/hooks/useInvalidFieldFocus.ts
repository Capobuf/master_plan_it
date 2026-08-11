import { useEffect } from "react";

export interface InvalidFieldFocusRequest {
  id: string;
}

export function useInvalidFieldFocus(request: InvalidFieldFocusRequest | null): void {
  useEffect(() => {
    if (request === null) return;

    const frame = window.requestAnimationFrame(() => {
      const field = document.getElementById(request.id);
      if (!(field instanceof HTMLElement)) return;

      field.scrollIntoView?.({ behavior: "smooth", block: "center" });
      field.focus({ preventScroll: true });
    });

    return () => window.cancelAnimationFrame(frame);
  }, [request]);
}
