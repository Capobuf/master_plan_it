import { useDropzone, type Accept, type FileRejection } from "react-dropzone";
import ComponentCard from "../../common/ComponentCard";

interface DropzoneProps {
  onFiles?: (files: File[]) => void;
  accept?: Accept;
  maxSize?: number;
  disabled?: boolean;
  title?: string;
  description?: string;
  embedded?: boolean;
  onRejected?: (rejections: FileRejection[]) => void;
}

export default function DropzoneComponent({
  onFiles = () => undefined,
  accept = { "image/png": [], "image/jpeg": [] },
  maxSize,
  disabled = false,
  title = "Carica file",
  description = "Trascina qui i file oppure selezionali dal dispositivo.",
  embedded = false,
  onRejected,
}: DropzoneProps) {
  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop: onFiles,
    onDropRejected: onRejected,
    accept,
    maxSize,
    disabled,
    multiple: false,
  });
  const content = <div {...getRootProps()} className={`cursor-pointer rounded-xl border border-dashed p-5 text-center transition ${isDragActive ? "border-brand-500 bg-brand-50 dark:bg-brand-500/10" : "border-gray-300 bg-gray-50 hover:border-brand-500 dark:border-gray-700 dark:bg-gray-900"} ${disabled ? "cursor-not-allowed opacity-60" : ""}`}>
    <input {...getInputProps()} aria-label={title} />
    <div className="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300" aria-hidden="true">
      <svg className="fill-current" width="24" height="24" viewBox="0 0 29 28"><path fillRule="evenodd" clipRule="evenodd" d="M14.5 3.9a.75.75 0 0 0-.55.24L8.57 9.53a.75.75 0 1 0 1.06 1.06l4.12-4.11v12.19a.75.75 0 0 0 1.5 0V6.48l4.12 4.11a.75.75 0 0 0 1.06-1.06l-5.35-5.34a.75.75 0 0 0-.58-.29ZM5.17 17.92a.75.75 0 0 0-.75.75v3.16a2.25 2.25 0 0 0 2.25 2.25h15.66a2.25 2.25 0 0 0 2.25-2.25v-3.16a.75.75 0 0 0-1.5 0v3.16a.75.75 0 0 1-.75.75H6.67a.75.75 0 0 1-.75-.75v-3.16a.75.75 0 0 0-.75-.75Z" /></svg>
    </div>
    <p className="mt-3 font-medium text-gray-800 dark:text-white/90">{isDragActive ? "Rilascia il file qui" : title}</p>
    <p className="mx-auto mt-1 max-w-lg text-sm text-gray-500 dark:text-gray-400">{description}</p>
    <span className="mt-3 inline-block text-sm font-medium text-brand-500 underline">Scegli file</span>
  </div>;

  return embedded ? content : <ComponentCard title="Upload">{content}</ComponentCard>;
}
