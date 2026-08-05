import { Head } from '@inertiajs/react'; import { TenantForm } from './Form'; export default function Create() { return <><Head title="Create tenant"/><TenantForm method="post"/></>; }
