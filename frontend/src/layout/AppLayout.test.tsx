import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import {
  createMemoryRouter,
  RouterProvider,
  useLocation,
  useNavigate,
} from "react-router";
import { beforeEach, describe, expect, it, vi } from "vitest";

import AppLayout from "./AppLayout";

vi.mock("./AppHeader", () => ({ default: () => <header>Shell annuale</header> }));
const planningGuard = vi.hoisted(() => ({
  dirty: false,
  confirm: vi.fn(() => true),
  consume: vi.fn(() => false),
}));
vi.mock("../context/PlanningYearContext", () => ({
  usePlanningYear: () => ({
    hasDirtySources: planningGuard.dirty,
    confirmDiscardChanges: planningGuard.confirm,
    consumeAuthorizedNavigation: planningGuard.consume,
  }),
}));

function LocationProbe() {
  const location = useLocation();
  const navigate = useNavigate();

  return <><p>Percorso {location.pathname}</p><button onClick={() => navigate(-1)}>Indietro</button></>;
}

describe("AppLayout", () => {
  beforeEach(() => {
    planningGuard.dirty = false;
    planningGuard.confirm.mockReset().mockReturnValue(true);
    planningGuard.consume.mockReset().mockReturnValue(false);
  });

  it("uses the compact shell without rendering a permanent sidebar", () => {
    const router = createMemoryRouter([{
      path: "/",
      element: <AppLayout />,
      children: [{ index: true, element: <p>Contenuto annuale</p> }],
    }]);
    const { container } = render(<RouterProvider router={router} />);
    expect(screen.getByText("Shell annuale")).toBeInTheDocument();
    expect(screen.getByText("Contenuto annuale")).toBeInTheDocument();
    expect(container.querySelector("aside")).toBeNull();
  });

  it("guards browser back navigation while an editor is dirty", async () => {
    planningGuard.dirty = true;
    planningGuard.confirm.mockReturnValueOnce(false).mockReturnValue(true);
    const router = createMemoryRouter([{
      path: "/",
      element: <AppLayout />,
      children: [
        { path: "registro", element: <p>Registro</p> },
        { path: "modifica", element: <LocationProbe /> },
      ],
    }], { initialEntries: ["/registro", "/modifica"], initialIndex: 1 });
    render(<RouterProvider router={router} />);

    fireEvent.click(screen.getByRole("button", { name: "Indietro" }));
    await waitFor(() => expect(planningGuard.confirm).toHaveBeenCalledTimes(1));
    expect(screen.getByText("Percorso /modifica")).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Indietro" }));
    expect(await screen.findByText("Registro")).toBeInTheDocument();
    expect(planningGuard.confirm).toHaveBeenCalledTimes(2);
  });
});
