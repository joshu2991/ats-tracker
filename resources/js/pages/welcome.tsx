import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import SEOHead from '../components/SEOHead';

export default function Welcome() {
    const { seo } = usePage<{ seo?: any }>().props;

    useEffect(() => {
        router.visit('/resume-checker', { replace: true });
    }, []);

    return (
        <>
            {seo && <SEOHead {...seo} />}
        </>
    );
}

