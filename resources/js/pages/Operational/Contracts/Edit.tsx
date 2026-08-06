import { Head } from "@inertiajs/react";
import ContractForm from "./Form";
import type { ContractFormPageProps } from "../../../types";

export default function Edit(props: ContractFormPageProps) {
    return (
        <>
            <Head title={`Edit ${props.contract?.title ?? "contract"}`} />
            <ContractForm {...props} method="put" />
        </>
    );
}
