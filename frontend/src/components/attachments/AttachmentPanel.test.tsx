import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { deleteAttachment, downloadAttachment, listAttachments, uploadAttachment, type AttachmentListResponse } from "../../api/attachments";
import AttachmentPanel from "./AttachmentPanel";

vi.mock("../../api/attachments", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../api/attachments")>();
  return { ...actual, listAttachments: vi.fn(), uploadAttachment: vi.fn(), downloadAttachment: vi.fn(), deleteAttachment: vi.fn() };
});

const emptyResponse: AttachmentListResponse = {
  data: [], meta: { used_bytes: "0", quota_bytes: "2147483648" }, abilities: { upload: true, download: true, delete: true },
};
const item = { id: 7, name: "Preventivo rete.pdf", mime_type: "application/pdf", extension: "pdf", size: 2048, uploaded_at: "2026-08-11T10:15:00Z", uploaded_by: { name: "Mario Rossi" } };

describe("AttachmentPanel", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listAttachments).mockResolvedValue(emptyResponse);
  });

  it("shows the Italian empty state and uploads one file with a visible busy state", async () => {
    let resolveUpload!: (value: Awaited<ReturnType<typeof uploadAttachment>>) => void;
    vi.mocked(uploadAttachment).mockReturnValue(new Promise((resolve) => { resolveUpload = resolve; }));
    render(<AttachmentPanel parent={{ kind: "project", projectId: 9 }} />);
    expect(await screen.findByText("Nessun allegato presente.")).toBeInTheDocument();

    const file = new File(["%PDF"], "Preventivo.pdf", { type: "application/pdf" });
    fireEvent.change(screen.getByLabelText("Trascina un allegato qui"), { target: { files: [file] } });
    expect(await screen.findByText("Caricamento in corso…")).toBeInTheDocument();
    resolveUpload({ data: item, meta: { used_bytes: "2048", quota_bytes: "2147483648" }, abilities: emptyResponse.abilities });

    expect(await screen.findByText("Preventivo rete.pdf")).toBeInTheDocument();
    expect(uploadAttachment).toHaveBeenCalledWith({ kind: "project", projectId: 9 }, file, expect.any(Function));
    expect(screen.queryByText("Caricamento in corso…")).not.toBeInTheDocument();
  });

  it("keeps upload errors visible and deletes only after confirmation", async () => {
    vi.mocked(listAttachments)
      .mockResolvedValueOnce({ ...emptyResponse, data: [item], meta: { ...emptyResponse.meta, used_bytes: "2048" } })
      .mockResolvedValue(emptyResponse);
    vi.mocked(uploadAttachment).mockRejectedValue(new Error("filesystem indisponibile"));
    vi.mocked(deleteAttachment).mockResolvedValue(undefined);
    render(<AttachmentPanel parent={{ kind: "contract", contractId: 4 }} />);
    expect(await screen.findByText("Preventivo rete.pdf")).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Elimina" }));
    expect(screen.getByText("Eliminare l'allegato?")).toBeInTheDocument();
    expect(deleteAttachment).not.toHaveBeenCalled();
    const deleteButtons = screen.getAllByRole("button", { name: "Elimina" });
    fireEvent.click(deleteButtons[deleteButtons.length - 1]);
    await waitFor(() => expect(deleteAttachment).toHaveBeenCalledWith({ kind: "contract", contractId: 4 }, 7));

    const file = new File(["%PDF"], "Errore.pdf", { type: "application/pdf" });
    fireEvent.change(screen.getByLabelText("Trascina un allegato qui"), { target: { files: [file] } });
    expect(await screen.findByText("filesystem indisponibile")).toBeInTheDocument();
  });

  it("downloads through a temporary browser object URL without exposing a permanent URL", async () => {
    vi.mocked(listAttachments).mockResolvedValue({ ...emptyResponse, data: [item] });
    vi.mocked(downloadAttachment).mockResolvedValue(new Blob(["payload"], { type: "application/pdf" }));
    const createObjectURL = vi.fn().mockReturnValue("blob:private");
    const revokeObjectURL = vi.fn();
    Object.defineProperty(URL, "createObjectURL", { configurable: true, value: createObjectURL });
    Object.defineProperty(URL, "revokeObjectURL", { configurable: true, value: revokeObjectURL });
    const click = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
    render(<AttachmentPanel parent={{ kind: "expense", expenseId: 42 }} />);

    fireEvent.click(await screen.findByRole("button", { name: "Scarica" }));
    await waitFor(() => expect(downloadAttachment).toHaveBeenCalledWith({ kind: "expense", expenseId: 42 }, 7));
    expect(createObjectURL).toHaveBeenCalled();
    expect(revokeObjectURL).toHaveBeenCalledWith("blob:private");
    click.mockRestore();
  });
});
