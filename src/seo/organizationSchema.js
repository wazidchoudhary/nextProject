export const organizationSchema = () => {
    return JSON.stringify({
        '@context': 'http://schema.org',
        '@type': 'Organization',
        name: 'Krapton',
        url: 'https://www.teflonbonehorncrafts.com',
        contactPoint: [
            {
                '@type': 'ContactPoint',
                telephone: '+91-9990510321',
                contactType: 'Customer service',
            },
            {
                '@type': 'ContactPoint',
                telephone: '+91-78385 00700',
                contactType: 'Customer support',
            },
        ],
        logo: 'https://www.teflonbonehorncrafts.com/assets/image/logo.webp',
        sameAs: [
            'https://www.facebook.com/A.SINTERNATIONAL252?mibextid=ZbWKwL',
            'https://twitter.com/ChodharyTanish',
        ],
    });
};
