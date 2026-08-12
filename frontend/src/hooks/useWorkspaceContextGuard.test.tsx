import { render } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { useWorkspaceContextGuard } from "./useWorkspaceContextGuard";

const registerDirtySource = vi.fn();
vi.mock("../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({ registerDirtySource }),
}));

function Probe({ dirty }: { dirty: boolean }) { useWorkspaceContextGuard("editor", dirty); return null; }

describe("useWorkspaceContextGuard", () => {
  it("registers changes and always releases its source", () => {
    const view = render(<Probe dirty />);
    expect(registerDirtySource).toHaveBeenCalledWith("editor", true);
    view.unmount();
    expect(registerDirtySource).toHaveBeenLastCalledWith("editor", false);
  });
});
