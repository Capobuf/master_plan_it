import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { afterEach } from "vitest";

const NativeRequest = globalThis.Request;

if (typeof NativeRequest === "function") {
  try {
    new NativeRequest("http://localhost", {
      signal: new AbortController().signal,
    });
  } catch {
    // Node 24's Request rejects AbortSignal instances created in jsdom's
    // separate realm. React Router supplies that signal for every navigation,
    // so retain the native request implementation while dropping only the
    // incompatible test-environment signal.
    class JsdomCompatibleRequest extends NativeRequest {
      constructor(input: RequestInfo | URL, init?: RequestInit) {
        if (init?.signal) {
          super(input, { ...init, signal: undefined });

          return;
        }

        super(input, init);
      }
    }

    Object.defineProperty(globalThis, "Request", {
      configurable: true,
      value: JsdomCompatibleRequest,
      writable: true,
    });
  }
}

afterEach(() => {
  cleanup();
});
