import type { SharedPageProps } from './index';

declare module '@inertiajs/core' {
    interface PageProps extends SharedPageProps {}
}
