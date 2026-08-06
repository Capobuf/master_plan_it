import { Head } from "@inertiajs/react";
import ContractForm from "./Form";
import type { ContractFormPageProps } from "../../../types";

export default function Create(props: ContractFormPageProps) {
    return (
        <>
            <Head title="New contract" />
            <ContractForm {...props} method="post" />
        </>
    );
}
