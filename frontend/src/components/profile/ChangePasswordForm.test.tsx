import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { changeOwnPassword } from "../../api/auth";
import ChangePasswordForm from "./ChangePasswordForm";

vi.mock("../../api/auth", () => ({ changeOwnPassword: vi.fn() }));

describe("ChangePasswordForm", () => {
  it("submits the current password and matching confirmation without exposing a recovery flow", async () => {
    vi.mocked(changeOwnPassword).mockResolvedValue(undefined);
    render(<ChangePasswordForm />);

    const inputs = screen.getAllByDisplayValue("");
    fireEvent.change(inputs[0], { target: { value: "Current!123" } });
    fireEvent.change(inputs[1], { target: { value: "NewPassword!456" } });
    fireEvent.change(inputs[2], { target: { value: "NewPassword!456" } });
    fireEvent.click(screen.getByRole("button", { name: "Salva password" }));

    await waitFor(() => {
      expect(changeOwnPassword).toHaveBeenCalledWith(
        "Current!123",
        "NewPassword!456",
        "NewPassword!456",
      );
    });
    expect(await screen.findByText("Password aggiornata")).toBeInTheDocument();
    expect(screen.queryByText(/password dimenticata/i)).not.toBeInTheDocument();
  });

  it("renders an observable failure without echoing submitted secrets", async () => {
    vi.mocked(changeOwnPassword).mockRejectedValue(new Error("Password corrente non valida"));
    render(<ChangePasswordForm />);

    const inputs = screen.getAllByDisplayValue("");
    fireEvent.change(inputs[0], { target: { value: "SecretCurrent" } });
    fireEvent.change(inputs[1], { target: { value: "SecretNext" } });
    fireEvent.change(inputs[2], { target: { value: "SecretNext" } });
    fireEvent.click(screen.getByRole("button", { name: "Salva password" }));

    expect(await screen.findByText("Modifica non riuscita")).toBeInTheDocument();
    expect(screen.queryByText("SecretCurrent")).not.toBeInTheDocument();
    expect(screen.queryByText("SecretNext")).not.toBeInTheDocument();
  });
});
