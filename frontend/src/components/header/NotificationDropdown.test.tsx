import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { listNotifications } from "../../api/notifications";
import NotificationDropdown from "./NotificationDropdown";

let allowed = true;

vi.mock("../../context/ApplicationContext", () => ({
  useApplicationContext: () => ({ hasAbility: () => allowed }),
}));
vi.mock("../../api/notifications", () => ({ listNotifications: vi.fn() }));

const emptyPage = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
  links: { first: null, last: null, prev: null, next: null },
};

describe("NotificationDropdown", () => {
  beforeEach(() => {
    allowed = true;
  });

  it("is absent without permission and does not query notifications", () => {
    allowed = false;
    render(<NotificationDropdown />);

    expect(screen.queryByRole("button", { name: "Apri notifiche" })).not.toBeInTheDocument();
    expect(listNotifications).not.toHaveBeenCalled();
  });

  it("loads only when opened and renders the actor empty state", async () => {
    vi.mocked(listNotifications).mockResolvedValue(emptyPage);
    render(<NotificationDropdown />);

    expect(listNotifications).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "Apri notifiche" }));

    expect(screen.getByText("Caricamento")).toBeInTheDocument();
    expect(await screen.findByText("Nessuna notifica")).toBeInTheDocument();
  });
});
