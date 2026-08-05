import { Head } from "@inertiajs/react";
import { TenantForm } from "./Form";
export default function Edit({ record }: { record: any }) {
    return (
        <>
            <Head title="Edit tenant" />
            <TenantForm tenant={record} method="put" />
        </>
    );
}
