import DropzoneComponent from "../form/form-elements/DropZone";

const attachmentAccept = {
  "application/pdf": [".pdf"],
  "image/jpeg": [".jpg", ".jpeg"],
  "image/png": [".png"],
  "text/csv": [".csv"],
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": [".xlsx"],
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document": [".docx"],
};

export default function AttachmentDropZone({ onFiles, onRejected, disabled }: { onFiles: (files: File[]) => void; onRejected: () => void; disabled: boolean }) {
  return <DropzoneComponent embedded onFiles={onFiles} onRejected={onRejected} disabled={disabled} accept={attachmentAccept} maxSize={10_485_760} title="Trascina un allegato qui" description="PDF, JPG, PNG, CSV, XLSX o DOCX · massimo 10 MiB. Il controllo definitivo avviene sul server." />;
}
