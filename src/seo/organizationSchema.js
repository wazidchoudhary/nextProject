export const organizationSchema = () => {
    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'Viking Craft Store - AS International',
        legalName: 'AS International',
        url: 'https://www.vikingcraftstore.com',
        description: 'Premium manufacturer and supplier of handcrafted bone, horn, and wood products including knife handles, drinking horns, guitar parts, and artisan crafts.',
        foundingDate: '2020',
        contactPoint: [
            {
                '@type': 'ContactPoint',
                telephone: '+91-9990510321',
                contactType: 'customer service',
                areaServed: 'Worldwide',
                availableLanguage: ['English', 'Hindi']
            },
            {
                '@type': 'ContactPoint',
                telephone: '+91-7838500700',
                contactType: 'sales',
                areaServed: 'Worldwide',
                availableLanguage: ['English', 'Hindi']
            },
        ],
        address: {
            '@type': 'PostalAddress',
            streetAddress: 'Khasra no 535-536, Garima Garden, Sahibabad',
            addressLocality: 'Ghaziabad',
            addressRegion: 'Uttar Pradesh',
            postalCode: '201001',
            addressCountry: 'IN'
        },
        logo: 'https://www.vikingcraftstore.com/assets/image/logo.webp',
        image: 'https://www.vikingcraftstore.com/assets/image/logo.webp',
        sameAs: [
            'https://www.facebook.com/A.SINTERNATIONAL252',
            'https://www.instagram.com/a.s_international252'
        ],
        priceRange: '$$'
    });
};
