import { Head } from "@inertiajs/react";
import ExpenseForm from "./Form";
import type { ExpenseFormPageProps } from "../../../types";

export default function Edit(props: ExpenseFormPageProps) {
    return (
        <>
            <Head title={`Edit ${props.expense?.title ?? "expense"}`} />
            <ExpenseForm {...props} method="put" />
        </>
    );
}
