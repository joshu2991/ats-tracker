import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export default function Welcome() {
    useEffect(() => {
        router.visit('/resume-checker', { replace: true });
    }, []);

    return null;
}

