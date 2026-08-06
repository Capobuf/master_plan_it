import { Head } from "@inertiajs/react";
import ExpenseForm from "./Form";
import type { ExpenseFormPageProps } from "../../../types";

export default function Create(props: ExpenseFormPageProps) {
    return (
        <>
            <Head title="New expense" />
            <ExpenseForm {...props} method="post" />
        </>
    );
}
